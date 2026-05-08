<?php
/**
 * HolidayAPI Class
 * 
 * Provides Philippine holiday data for calendar and scheduling
 * Uses built-in holiday data for reliability
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

class HolidayAPI {
    private $api_key;
    private $base_url = 'https://holidayapi.com/v1/holidays';
    private $country = 'PH'; // Philippines
    
    public function __construct($api_key = null) {
        // API key optional - we use fallback data
        $this->api_key = $api_key ?: getenv('HOLIDAY_API_KEY');
    }
    
    /**
     * Get holidays for a specific year
     * 
     * @param int|null $year Year (defaults to current year)
     * @return array Array of holidays
     */
    public function getHolidays($year = null) {
        if (!$year) {
            $year = date('Y');
        }
        
        // Use built-in Philippine holidays for reliability
        return $this->getPhilippineHolidays($year);
    }
    
    /**
     * Get comprehensive Philippine holidays for any year
     * 
     * @param int $year Year
     * @return array Array of holidays
     */
    private function getPhilippineHolidays($year) {
        // Calculate Easter dates for the year
        $easter = $this->calculateEaster($year);
        $maundy_thursday = date('Y-m-d', strtotime($easter . ' -3 days'));
        $good_friday = date('Y-m-d', strtotime($easter . ' -2 days'));
        $black_saturday = date('Y-m-d', strtotime($easter . ' -1 day'));
        
        // Calculate National Heroes Day (last Monday of August)
        $heroes_day = date('Y-m-d', strtotime('last monday of august ' . $year));
        
        $holidays = [
            // Fixed Regular Holidays
            ['name' => 'New Year\'s Day', 'date' => $year . '-01-01', 'type' => 'regular'],
            ['name' => 'Araw ng Kagitingan (Day of Valor)', 'date' => $year . '-04-09', 'type' => 'regular'],
            ['name' => 'Labor Day', 'date' => $year . '-05-01', 'type' => 'regular'],
            ['name' => 'Independence Day', 'date' => $year . '-06-12', 'type' => 'regular'],
            ['name' => 'Bonifacio Day', 'date' => $year . '-11-30', 'type' => 'regular'],
            ['name' => 'Christmas Day', 'date' => $year . '-12-25', 'type' => 'regular'],
            ['name' => 'Rizal Day', 'date' => $year . '-12-30', 'type' => 'regular'],
            
            // Variable Regular Holidays
            ['name' => 'Maundy Thursday', 'date' => $maundy_thursday, 'type' => 'regular'],
            ['name' => 'Good Friday', 'date' => $good_friday, 'type' => 'regular'],
            ['name' => 'National Heroes Day', 'date' => $heroes_day, 'type' => 'regular'],
            
            // Special Non-Working Holidays
            ['name' => 'People Power Anniversary', 'date' => $year . '-02-25', 'type' => 'special'],
            ['name' => 'Black Saturday', 'date' => $black_saturday, 'type' => 'special'],
            ['name' => 'Ninoy Aquino Day', 'date' => $year . '-08-21', 'type' => 'special'],
            ['name' => 'All Saints\' Day', 'date' => $year . '-11-01', 'type' => 'special'],
            ['name' => 'All Souls\' Day', 'date' => $year . '-11-02', 'type' => 'special'],
            ['name' => 'Immaculate Conception', 'date' => $year . '-12-08', 'type' => 'special'],
            ['name' => 'Christmas Eve', 'date' => $year . '-12-24', 'type' => 'special'],
            ['name' => 'New Year\'s Eve', 'date' => $year . '-12-31', 'type' => 'special'],
        ];
        
        // Add year-specific holidays (Islamic holidays vary by year)
        if ($year == 2024) {
            $holidays[] = ['name' => 'Eid al-Fitr', 'date' => '2024-04-10', 'type' => 'special'];
            $holidays[] = ['name' => 'Eid al-Adha', 'date' => '2024-06-17', 'type' => 'special'];
        } elseif ($year == 2025) {
            $holidays[] = ['name' => 'Eid al-Fitr', 'date' => '2025-03-31', 'type' => 'special'];
            $holidays[] = ['name' => 'Eid al-Adha', 'date' => '2025-06-07', 'type' => 'special'];
        } elseif ($year == 2026) {
            $holidays[] = ['name' => 'Eid al-Fitr', 'date' => '2026-03-20', 'type' => 'special'];
            $holidays[] = ['name' => 'Eid al-Adha', 'date' => '2026-05-27', 'type' => 'special'];
        }
        
        // Format holidays
        $formatted = [];
        foreach ($holidays as $holiday) {
            $formatted[] = [
                'name' => $holiday['name'],
                'date' => $holiday['date'],
                'type' => $holiday['type'],
                'description' => $holiday['name'] . ' - Philippine Holiday',
                'year' => $year,
                'is_recurring' => 1
            ];
        }
        
        // Sort by date
        usort($formatted, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $formatted;
    }
    
    /**
     * Calculate Easter Sunday for a given year
     * 
     * @param int $year Year
     * @return string Date in Y-m-d format
     */
    private function calculateEaster($year) {
        return date('Y-m-d', easter_date($year));
    }
    
    /**
     * Get holidays for a specific month
     * 
     * @param int|null $month Month (1-12)
     * @param int|null $year Year
     * @return array Array of holidays
     */
    public function getHolidaysForMonth($month = null, $year = null) {
        if (!$month) $month = date('n');
        if (!$year) $year = date('Y');
        
        $allHolidays = $this->getHolidays($year);
        $monthHolidays = [];
        
        foreach ($allHolidays as $holiday) {
            $holidayMonth = date('n', strtotime($holiday['date']));
            if ($holidayMonth == $month) {
                $monthHolidays[] = $holiday;
            }
        }
        
        return $monthHolidays;
    }
    
    /**
     * Get upcoming holidays
     * 
     * @param int $limit Number of holidays to return
     * @return array Array of upcoming holidays
     */
    public function getUpcomingHolidays($limit = 5) {
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        
        // Get holidays for current and next year
        $currentYearHolidays = $this->getHolidays($currentYear);
        $nextYearHolidays = $this->getHolidays($nextYear);
        
        $allHolidays = array_merge($currentYearHolidays, $nextYearHolidays);
        
        // Filter upcoming holidays
        $today = date('Y-m-d');
        $upcomingHolidays = [];
        
        foreach ($allHolidays as $holiday) {
            if ($holiday['date'] >= $today) {
                $upcomingHolidays[] = $holiday;
            }
        }
        
        // Sort by date and limit
        usort($upcomingHolidays, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return array_slice($upcomingHolidays, 0, $limit);
    }
    
    /**
     * Check if a specific date is a holiday
     * 
     * @param string $date Date in Y-m-d format
     * @return array|false Holiday data or false if not a holiday
     */
    public function isHoliday($date) {
        $year = date('Y', strtotime($date));
        $holidays = $this->getHolidays($year);
        
        foreach ($holidays as $holiday) {
            if ($holiday['date'] === $date) {
                return $holiday;
            }
        }
        
        return false;
    }
    
    /**
     * Get all holidays formatted for calendar display
     * 
     * @param int|null $year Year
     * @return array Array of calendar events
     */
    public function getAllHolidaysForCalendar($year = null) {
        if (!$year) $year = date('Y');
        
        $holidays = $this->getHolidays($year);
        $calendarEvents = [];
        
        foreach ($holidays as $holiday) {
            $calendarEvents[] = [
                'id' => 'holiday_' . str_replace('-', '', $holiday['date']),
                'title' => ($holiday['type'] === 'regular' ? '🏛️' : '🎉') . ' ' . $holiday['name'],
                'start' => $holiday['date'],
                'allDay' => true,
                'className' => 'event-holiday',
                'backgroundColor' => $holiday['type'] === 'regular' ? '#dc3545' : '#fd7e14',
                'borderColor' => $holiday['type'] === 'regular' ? '#dc3545' : '#fd7e14',
                'textColor' => 'white',
                'extendedProps' => [
                    'description' => $holiday['description'],
                    'type' => 'holiday',
                    'holidayType' => $holiday['type'],
                    'source' => 'holiday_api'
                ]
            ];
        }
        
        return $calendarEvents;
    }
}
