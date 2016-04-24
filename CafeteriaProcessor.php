<?php

use Luracast\Restler\RestException;

class CafeteriaProcessor extends Processor
{

    /**
     * Returns raw cafeteria menu data & put it into a text file
     *
     * @return string Raw data
     * @throws RestException Throws a 412 "Precondition Failed" exception when pdftohtml library doesn't work
     */
    public function getRawData()
    {
        file_put_contents("yemek_listesi.pdf", $this->fetchUrl(Constants::MENU_URL, [
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => true
        ]));

        exec("pdftohtml yemek_listesi.pdf temp && rm temp*png && rm temp.html && rm temp_ind.html && mv temps.html " . Constants::MENU_RAWDATA_FILENAME);

        if (!file_exists(Constants::MENU_RAWDATA_FILENAME)) {
            throw new RestException(412, "Something went terribly wrong, pdftotext library didn't work as expected.");
        } else {
            $textOutput = strip_tags(html_entity_decode(file_get_contents(Constants::MENU_RAWDATA_FILENAME), ENT_QUOTES, "UTF-8"));
            $textOutput = preg_replace("/^\n+|^[\t\s]*\n+/m", null, $textOutput);
            file_put_contents(Constants::MENU_RAWDATA_FILENAME, $textOutput);
        }

        //unlink("yemek_listesi.pdf");

        return $textOutput;
    }


    /**
     * @return mixed
     * @throws RestException Throws a 412 "Precondition Failed" exception when pdftohtml library doesn't work
     * or when new menu couldn't be written to the DB.
     */
    public static function fetchNewMenu()
    {
        if (!file_exists(Constants::MENU_RAWDATA_FILENAME)) {
            $rawData = explode("\n", self::getRawData());
        } else {
            $rawData = explode("\n", file_get_contents(Constants::MENU_RAWDATA_FILENAME));
        }
        array_walk($rawData, function (&$val) {
            return $val = preg_replace('#\s?/\s?#', ' / ', preg_replace('/\s+/', " ", trim(str_replace([chr(0xC2), chr(0xA0), chr(0x0B)], " ", $val))));
        });

        $rawData = array_values(array_diff($rawData, ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"])); // strip off days from raw menu data

        $db = DB::getInstance();

        /*
         * Parse dates & menus
         */
        $dayLines = preg_grep("#[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}#si", $rawData); // find which lines contain the date
        $foodList = [];
        foreach (array_keys($dayLines) as $k => $line) {
            $tempArray = [];
            $day = implode("/", array_reverse(explode(".", $dayLines[$line]))); // Y/m/d formatted day

            $tempArray[Constants::LUNCH_MENU_IDENTIFIER_CHAR][] = $rawData[$line - 2];
            $tempArray[Constants::DINNER_MENU_IDENTIFIER_CHAR][] = $rawData[$line - 1];

            for ($i = 0; $i < 7; $i += 2) {
                $tempArray[Constants::LUNCH_MENU_IDENTIFIER_CHAR][] = $rawData[$line + 1 + $i];
                $tempArray[Constants::DINNER_MENU_IDENTIFIER_CHAR][] = $rawData[$line + 2 + $i];
            }

            $textToCheck = join("", $tempArray[Constants::LUNCH_MENU_IDENTIFIER_CHAR]) . join("", $tempArray[Constants::DINNER_MENU_IDENTIFIER_CHAR]);
            if (preg_match("#[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}#si", $textToCheck)) continue; // no full-valid menu info

            $foodList[$day] = $tempArray;
        }

        /*
         * Parse calories, insert into the database
         */
        $calorieLines = preg_grep('#\\skcal$#si', $rawData);
        foreach ($calorieLines as $line => $calorie) {
            $calorie = (int)strtok($calorie, " "); // get before the first space, which is the actual calorie value
            $foodName = $rawData[$line - 1];
            $checkQuery = $db->prepare("SELECT * FROM calories WHERE food_name = :food");
            $checkQuery->execute([
                ":food" => $foodName
            ]);
            if ($checkQuery->rowCount() == 0) { // not added before
                $insertQuery = $db->prepare("INSERT INTO calories (food_name, food_calorie) VALUES (:food, :calorie)");
                if ($insertQuery->execute([
                        ":food" => $foodName,
                        ":calorie" => $calorie
                    ]) === false
                ) {
                    throw new RestException(412, "Calories couldn't be written to the database.");
                }
            }
        }

        /*
         * Insert into
         */
        foreach ($foodList as $day => $v) foreach ($v as $meal => $foods) {
            $checkQuery = $db->prepare("SELECT * FROM menus WHERE day = :day AND meal = :meal");
            $checkQuery->execute([
                ":day" => $day,
                ":meal" => $meal
            ]);
            if ($checkQuery->rowCount() == 0) { // not added before
                $insertQuery = $db->prepare("INSERT INTO menus (day, meal, list) VALUES (:day, :meal, :list)");
                if ($insertQuery->execute([
                        ":day" => $day,
                        ":meal" => $meal,
                        ":list" => json_encode($foods)
                    ]) === false
                ) {
                    throw new RestException(412, "New menu couldn't be written to the database.");
                }
            }

        }

        return $foodList;
    }

    /**
     * Get cafeteria menu for a given date
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Lunch & dinner menus in an array, with "lunch" & "dinner" as keys
     * @throws RestException
     */
    public static function getMenu($year, $month, $day)
    {
        if (!is_numeric($year) || !is_numeric($month) || !is_numeric($day))
            throw new RestException(400, "Not a valid date.");
        $db = DB::getInstance();
        $query = $db->prepare("SELECT * FROM menus WHERE day = :day");
        if ($query->execute([
                ":day" => $year . "/" . $month . "/" . $day
            ]) === false
        )
            throw new RestException(412, "Menu data could not be retrieved from database.");
        $data = $query->fetchAll();
        $lunch = [];
        $dinner = [];
        if (isset($data[0]['list']) && $data[0]['meal'] == Constants::LUNCH_MENU_IDENTIFIER_CHAR) {
            $lunch = json_decode($data[0]['list'], true);
            $dinner = json_decode($data[1]['list'], true);
        } else if (isset($data[1]['list'])) {
            $lunch = json_decode($data[1]['list'], true);
            $dinner = json_decode($data[0]['list'], true);
        }
        return [
            'lunch' => $lunch,
            'dinner' => $dinner
        ];
    }

    /**
     * Get calorie for all foods in the specified day's menu
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Calories of each food in an array
     * @throws RestException
     */
    public static function getCaloriesForDay($year, $month, $day)
    {
        if (!is_numeric($year) || !is_numeric($month) || !is_numeric($day))
            throw new RestException(400, "Date is not valid.");
        return self::getCaloriesForFood(self::getMenu($year, $month, $day));
    }

    /**
     * Get calorie count(s) for the specified food(s)
     *
     * @param string|array $foodName Name of the food(s)
     * @return array Calories of each food in an array
     * @throws RestException
     */
    public static function getCaloriesForFood($foodName)
    {
        if (is_string($foodName)) $foodName = (array)$foodName;
        else if (is_array($foodName)) {
            if (isset($foodName["lunch"]) && isset($foodName["dinner"])) $foodName = array_merge($foodName["lunch"], $foodName["dinner"]);
        } else {
            throw new RestException(400, "Input is not valid.");
        }

        if (count($foodName) == 0) return new stdClass(); // return an empty class instead of an empty array to solve casting (to Dictionary) problem in json decoding with swift

        // separate multiple foods
        list($groupedFoods, $foodName) = array_values(self::splitMultipleFoods($foodName));

        $data = self::getDataFromDBWithFoodName("calories", $foodName);

        $calorieList = [];
        foreach ($data as $infoTuple) {
            $calorieList[$infoTuple["food_name"]] = $infoTuple["food_calorie"];
        }

        $returnList = [];
        foreach ($groupedFoods as $_foodName) {
            if (!is_array($_foodName)) {
                if (isset($calorieList[$_foodName]))
                    $returnList[$_foodName] = $calorieList[$_foodName];
            } else {
                $caloriesString = "";
                array_walk($_foodName, function ($val) use (&$caloriesString, $calorieList) {
                    if (isset($calorieList[$val]))
                        $caloriesString .= $calorieList[$val] . " / ";
                    else {
                        $caloriesString .= "NA" . " / ";
                    }
                });
                $caloriesString = rtrim($caloriesString, "/ ");
                $returnList[implode(" / ", $_foodName)] = $caloriesString;
            }
        }

        return !empty($returnList) ? $returnList : new stdClass();

    }

    /**
     * Get images for all foods in the specified day's menu
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Images of each food in an array
     * @throws RestException
     */
    public static function getImagesForDay($year, $month, $day)
    {
        if (!is_numeric($year) || !is_numeric($month) || !is_numeric($day))
            throw new RestException(400, "Date is not valid.");
        return self::getImagesForFood(self::getMenu($year, $month, $day));
    }

    /**
     * Get image(s) for the specified food(s)
     *
     * @param string|array $foodName Name of the food(s)
     * @return array Image(s) of each food in an array
     * @throws RestException
     */
    public static function getImagesForFood($foodName)
    {
        if (is_string($foodName)) $foodName = (array)$foodName;
        else if (is_array($foodName)) {
            if (isset($foodName["lunch"]) && isset($foodName["dinner"])) $foodName = array_merge($foodName["lunch"], $foodName["dinner"]);
        } else {
            throw new RestException(400, "Input is not valid.");
        }

        if (count($foodName) == 0) return new stdClass();

        // separate multiple foods
        list($groupedFoods, $foodName) = array_values(self::splitMultipleFoods($foodName));

        $data = self::getDataFromDBWithFoodName("images", $foodName);

        $imageList = [];
        foreach ($data as $infoTuple) {
            $imageList[$infoTuple["food_name"]] = $infoTuple["food_image"];
        }

        $returnList = [];
        foreach ($groupedFoods as $_foodName) {
            if (!is_array($_foodName)) {
                if (isset($imageList[$_foodName]))
                    $returnList[$_foodName] = $imageList[$_foodName];
            } else {
                $imageArray = [];
                array_walk($_foodName, function ($val) use (&$imageArray, $imageList) {
                    if (isset($imageList[$val]))
                        $imageArray[] = $imageList[$val];
                    else {
                        $imageArray[] = null;
                    }
                });
                $returnList[implode(" / ", $_foodName)] = $imageArray;
            }
        }

        return !empty($returnList) ? $returnList : new stdClass();
    }


    /**
     * Split multiple foods on the array with delimiter "/"
     *
     * @param array $foodName Foodname list that contains multiple foods on a row
     * @return array "splitted" & "grouped" multiple foods on an array - with the exact keys as written
     */
    private static function splitMultipleFoods(array $foodName)
    {
        // separate multiple foods
        $newFoodName = $foodName; // array with multiple foods separated
        $toMerge = [];
        foreach ($foodName as $key => $val) {
            if (strpos($val, "/") !== false) { // multiple foods on 1 row
                $foods = array_map('trim', explode("/", $val));
                unset($foodName[$key]);
                $newFoodName[$key] = $foods;
                $toMerge = array_merge($toMerge, $foods);
            }
        }
        $foodName = array_merge($foodName, $toMerge);
        unset($toMerge);
        return [
            "grouped" => $newFoodName,
            "splitted" => $foodName
        ];
    }

    /**
     * Get data from DB with table name & food name(s)
     *
     * @param string $tableName Table name
     * @param string|array $foodName Food name(s)
     * @return array Data
     * @throws RestException
     */
    private static function getDataFromDBWithFoodName(string $tableName, $foodName)
    {
        if (is_string($foodName)) $foodName = (array)$foodName;
        else if (is_array($foodName)) {
            if (isset($foodName["lunch"]) && isset($foodName["dinner"])) $foodName = array_merge($foodName["lunch"], $foodName["dinner"]);
        } else {
            throw new RestException(400, "Input is not valid.");
        }

        if (count($foodName) == 0) return new stdClass();
        
        $db = DB::getInstance();

        // concatenation is made on purpose. (PHPStorm's red alerts)
        $query = $db->prepare("SELECT * FROM {$tableName} WHERE food_name IN (" . implode(", ", preg_filter('/^/', ':food', range(0, count($foodName) - 1))) . ")");

        for ($i = 0; $i < count($foodName); $i++) {
            $query->bindValue(":food" . $i, $foodName[$i]);
        }

        if ($query->execute() === false) {
            throw new RestException(412, "Data could not be retrieved from database.");
        }

        $data = $query->fetchAll();

        return $data;
    }


}
