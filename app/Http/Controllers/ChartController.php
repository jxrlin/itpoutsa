<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class ChartController extends Controller
{
    public function index()
    {
        // Get the current month in 'YYYY-MM' format
        $currentMonth = Carbon::now()->format('Y-m');

        $today = Carbon::now()->format('Y-m-d'); // Get current date

        // Get the last year's value dynamically
        $lastYear = date('Y') - 1;

        // Get the monthly sales data for the last year
        $salesData = DB::table('sales_invoices')
            ->select(DB::raw("DATE_FORMAT('%Y-%m', sale_date) AS sale_month"), DB::raw("SUM(total_mmk) AS total_monthly_sales"))
            ->whereYear('sale_date', $lastYear) // Filter data for the last year
            ->groupBy(DB::raw("DATE_FORMAT('%Y-%m', sale_date)"))
            ->orderBy(DB::raw("DATE_FORMAT('%Y-%m', sale_date)"))
            ->get();

        // Prepare the labels and data for the chart
        $labels = $salesData->pluck('sale_month')->toArray();
        $data = $salesData->pluck('total_monthly_sales')->toArray();

        // Prepare the data for the Flask API (same format that you send)
        $flaskData = $salesData->map(function($item) {
            return [
                'sale_date' => $item->sale_month,
                'total_mmk' => $item->total_monthly_sales
            ];
        })->toArray();

        // Create a Guzzle client to send the request to Flask
        $client = new Client();

        // Model Prediction Data With Server Render
        try {
            // Send the sales data to Flask
            $response = $client->post('https://sale-prediction-model.onrender.com/predict-sales', [
                'json' => $flaskData, // Send the filtered sales data (last year)
                'verify' => false, // Disable for testing
            ]);

            // Get the response from Flask
            $responseData = json_decode($response->getBody()->getContents(), true);

            // Extract only the predictions from the response
            $predictions = $responseData['predictions'];

            // If no predictions are received or if the predictions data is empty, set a fallback value
            if (empty($predictions)) {
                $predictions = [];
            }

        } catch (\Exception $e) {
            // Log the error message and set predictions to empty or fallback data
            error_log("Error connecting to Flask server: " . $e->getMessage());
            $predictions = []; // Set default empty predictions
        }

        $previousMonth = now()->subMonth()->format('Y-m');

        // Get the sales data for the current month
        $shopDataCurrMonth = DB::table('sales_invoices as si')
            ->select(DB::raw("ps.partner_shops_name as shop_name"),
                DB::raw("DATE_FORMAT('%Y-%m', si.sale_date) AS sale_month"),
                DB::raw("SUM(si.quantity) AS total_quantity"))
            ->join('partner_shops as ps', 'si.partner_shops_id', '=', 'ps.partner_shops_id')
            ->whereRaw("DATE_FORMAT('%Y-%m', si.sale_date) = ?", [$currentMonth])
            ->groupBy(DB::raw("ps.partner_shops_name"), DB::raw("DATE_FORMAT('%Y-%m', si.sale_date)"))
            ->orderBy('ps.partner_shops_name')
            ->get();

        $shopLabelsCurrMonth = $shopDataCurrMonth->pluck('shop_name')->toArray();  // x-axis: shop names
        $shopDataValuesCurrMonth = $shopDataCurrMonth->pluck('total_quantity')->toArray();  // y-axis: total quantities sold

        // Get the sales data for the previous month
        $shopDataPrevMonth = DB::table('sales_invoices as si')
            ->select(DB::raw("ps.partner_shops_name as shop_name"),
                DB::raw("DATE_FORMAT('%Y-%m', si.sale_date) AS sale_month"),
                DB::raw("SUM(si.quantity) AS total_quantity"))
            ->join('partner_shops as ps', 'si.partner_shops_id', '=', 'ps.partner_shops_id')
            ->whereRaw("DATE_FORMAT('%Y-%m', si.sale_date) = ?", [$previousMonth])
            ->groupBy(DB::raw("ps.partner_shops_name"), DB::raw("DATE_FORMAT('%Y-%m', si.sale_date)"))
            ->orderBy('ps.partner_shops_name')
            ->get();

        $shopLabelsPrevMonth = $shopDataPrevMonth->pluck('shop_name')->toArray();  // x-axis: shop names
        $shopDataValuesPrevMonth = $shopDataPrevMonth->pluck('total_quantity')->toArray();  // y-axis: total quantities sold

        // shopLabelsPrevMonth, shopDataValuesPrevMonth, hourlySales ka null fik ny

        // Fetch hourly sales for the current day
        $hourlySales = DB::table('sales_invoices')
            ->select(DB::raw("DATE_FORMAT(created_at, '%H') AS sale_hour"), DB::raw("SUM(total_mmk) AS hourly_sales"))
            ->whereRaw("DATE(created_at) = ?", '2024-12-30') // Filter for today's sales
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%H')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%H')"))
            ->get();


        // Convert data to arrays for Highcharts
        $salesHours = $hourlySales->pluck('sale_hour')->toArray();
        $salesValues = $hourlySales->pluck('hourly_sales')->toArray();

        // Find peak sales hour
        // ✅ Handle empty sales data to prevent "max()" error
        if (!empty($salesValues)) {
            $maxSale = max($salesValues);
            $maxSaleHour = $salesHours[array_search($maxSale, $salesValues)];
        } else {
            $maxSale = 0; // No sales
            $maxSaleHour = null;
        }

        $monthlySales = DB::table('sales_invoices')
            ->select(DB::raw("DATE_FORMAT(created_at, '%m') AS sale_month"), DB::raw("SUM(total_mmk) AS monthly_sales"))
            ->whereRaw("DATE(created_at) >= ?", '2024-01-01')  // Filter for sales from January 2024 onward
            ->whereRaw("DATE(created_at) <= ?", '2024-12-31') // Filter for sales until December 2024
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%m')"))
            ->get();

        // Prepare data for chart
        // Prepare data for chart
        $months = $monthlySales->pluck('sale_month');

        // Convert numeric months to month names
        $monthNames = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'May',
            '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Oct',
            '11' => 'Nov', '12' => 'Dec'
        ];

        $months = $months->map(function ($month) use ($monthNames) {
            return $monthNames[$month];
        });
        $salesValuesMonth = $monthlySales->pluck('monthly_sales');

        // Find the peak sales month
        $maxSaleMonth = $salesValuesMonth->max();
        $maxSaleMonthIndex = $salesValuesMonth->search($maxSaleMonth);
        $maxSaleMonth = $months[$maxSaleMonthIndex];

        $dailySales = DB::table('sales_invoices')
            ->select(DB::raw("DATE_FORMAT(created_at, '%d') AS sale_day"), DB::raw("SUM(total_mmk) AS daily_sales"))
            ->whereRaw("DATE_FORMAT(created_at, '%m') = ?", '01') // Example for January, you can change this dynamically
            ->whereRaw("DATE_FORMAT('%Y', created_at) = ?", '2024') // Example for year 2024
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%d')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%d')"))
            ->get();

        // Prepare data for chart
        $days = $dailySales->pluck('sale_day');
        $salesValuesDay = $dailySales->pluck('daily_sales');

        $maxSaleDay = $salesValuesDay->max();
        $maxSaleDayIndex = $salesValuesDay->search($maxSaleDay);
        
        // Ensure we have valid data before accessing the array
        if ($maxSaleDayIndex !== false && $maxSaleDayIndex !== null) {
            $maxSaleDay = $days[$maxSaleDayIndex];
        } else {
            $maxSaleDay = null; // Set to null or a default value
        }
        

        // Passing the sales data and predictions to the view
        return view('adm_dashboard', [
            'labels' => $labels,
            'data' => $data,
            'predictions' => $predictions,
            'shopLabelsCurrMonth' => $shopLabelsCurrMonth,
            'shopDataValuesCurrMonth' => $shopDataValuesCurrMonth,
            'shopLabelsPrevMonth' => $shopLabelsPrevMonth,
            'shopDataValuesPrevMonth' => $shopDataValuesPrevMonth,
            'salesHours' => $salesHours,
            'salesValues' => $salesValues,
            'maxSale' => $maxSale,
            'maxSaleHour' => $maxSaleHour,
            'months' => $months,
            'monthlySales' => $salesValues, // Pass the monthly sales data to the view
            'maxSaleMonth' => $maxSaleMonth, // Pass the peak sales month
            'days' => $days,
            'dailySales' => $salesValuesDay, // Pass the daily sales data to the view
            'maxSaleDay' => $maxSaleDay, // Pass the peak sales day
        ]);
    }
}
