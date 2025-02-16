<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChartController extends Controller
{
    public function salesData()
    {
        $salesData = DB::table('sales_invoice')
            ->select(DB::raw("DATE_FORMAT(sale_date, '%Y-%m') AS sale_month"), DB::raw("SUM(total_mmk) AS total_monthly_sales"))
            ->groupBy(DB::raw("DATE_FORMAT(sale_date, '%Y-%m')"))
            ->get();

        return response()->json($salesData);
    }

    public function shopSales()
    {
        $shopSalesData = DB::table('sales_invoice AS si')
            ->join('partner_shops AS ps', 'si.partner_shops_id', '=', 'ps.partner_shops_id')
            ->select(
                DB::raw("ps.partner_shops_name as shop_name"),
                DB::raw("DATE_FORMAT(si.sale_date, '%Y-%m') AS sale_month"),
                DB::raw("SUM(si.quantity) AS total_quantity")
            )
            ->groupBy(DB::raw("ps.partner_shops_name, DATE_FORMAT(si.sale_date, '%Y-%m')"))
            ->get();

        return response()->json($shopSalesData);
    }

    public function hourlySales()
    {
        $hourlySalesData = DB::table('sales_invoice')
            ->select(DB::raw("HOUR(created_at) AS sale_hour"), DB::raw("SUM(total_mmk) AS hourly_sales"))
            ->groupBy(DB::raw("HOUR(created_at)"))
            ->get();

        return response()->json($hourlySalesData);
    }

    public function monthlyAggregation()
    {
        $monthlySalesData = DB::table('sales_invoice')
            ->select(DB::raw("MONTH(created_at) AS sale_month"), DB::raw("SUM(total_mmk) AS monthly_sales"))
            ->groupBy(DB::raw("MONTH(created_at)"))
            ->get();

        return response()->json($monthlySalesData);
    }

    public function dailySales()
    {
        $dailySalesData = DB::table('sales_invoice')
            ->select(DB::raw("DAY(created_at) AS sale_day"), DB::raw("SUM(total_mmk) AS daily_sales"))
            ->groupBy(DB::raw("DAY(created_at)"))
            ->get();

        return response()->json($dailySalesData);
    }

    public function predictSales()
    {
        $salesData = DB::table('sales_invoice')
            ->select('sale_date', 'total_mmk')
            ->orderBy('sale_date')
            ->get();

        $formattedData = $salesData->map(function ($sale) {
            return [
                'date' => $sale->sale_date,
                'sales' => $sale->total_mmk
            ];
        });

        $response = Http::post('http://127.0.0.1:8000/predict', ['sales_data' => $formattedData]);

        return response()->json($response->json());
    }
}
