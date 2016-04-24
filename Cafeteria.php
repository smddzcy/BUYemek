<?php

use Luracast\Restler\RestException;

/**
 * Class Cafeteria
 *
 * Controller of the Cafeteria API
 */
class Cafeteria
{

    /**
     * Get cafeteria menu for a given date
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Lunch & dinner menus in an array, with "lunch" & "dinner" as keys
     * @throws RestException
     * @url GET menu/{year}/{month}/{day}
     */
    public function getMenu($year, $month, $day)
    {
        return CafeteriaProcessor::getMenu($year, $month, $day);
    }

    /**
     * Get calorie for all foods in the specified day's menu
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Calories of each food in an array, with "lunch" & "dinner" as keys
     * @throws RestException
     * @url GET calories/{year}/{month}/{day}
     */
    public static function getCaloriesForDay($year, $month, $day)
    {
        return CafeteriaProcessor::getCaloriesForDay($year, $month, $day);
    }

    /**
     * Get calorie count(s) for the specified food(s)
     *
     * @param string|array $foodName Name of the food(s)
     * @return array Calories of each food in an array
     * @throws RestException
     * @url GET calories/{foodName}
     */
    public static function getCaloriesForFood($foodName)
    {
        return CafeteriaProcessor::getCaloriesForFood($foodName);
    }

    /**
     * Get images for all foods in the specified day's menu
     *
     * @param string $year Year
     * @param string $month Month
     * @param string $day Day
     * @return array Images of each food in an array, with "lunch" & "dinner" as keys
     * @throws RestException
     * @url GET images/{year}/{month}/{day}
     */
    public static function getImagesForDay($year, $month, $day)
    {
        return CafeteriaProcessor::getImagesForDay($year, $month, $day);
    }

    /**
     * Get image(s) for the specified food(s)
     *
     * @param string|array $foodName Name of the food(s)
     * @return array Images of each food in an array
     * @throws RestException
     * @url GET images/{foodName}
     */
    public static function getImagesForFood($foodName)
    {
        return CafeteriaProcessor::getImagesForFood($foodName);
    }

    /**
     * Fetch the new menu from BOUN
     * Requires a valid API key
     *
     * @param $apiKey API key
     * @throws RestException
     * @return array Status code and date
     * @url GET fetch/{apiKey}
     */
    public static function fetchNewMenu($apiKey)
    {
        $db = DB::getInstance();
        $apiCheckQuery = $db->prepare("SELECT * FROM `keys` WHERE `api-key` = :key");
        if ($apiCheckQuery->execute([
                ":key" => $apiKey
            ]) === false
        ) {
            throw new RestException(412, "API key could not be checked with the database entries.");
        };
        if ($apiCheckQuery->rowCount() == 0) throw new RestException(403, "API key is not valid.");
        else {
            CafeteriaProcessor::fetchNewMenu();
            return [
                "status" => 200,
                "date" => date('Y/m/d')
            ];
        }
    }


}