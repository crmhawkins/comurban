<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Message;
use App\Models\Call;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $roles = $user->roles;

        // Total consultas del último mes (WhatsApp recibidos + Llamadas)
        $lastMonth = Carbon::now()->subMonth();
        $whatsappLastMonth = Message::where('direction', 'inbound')
            ->where('created_at', '>=', $lastMonth)
            ->count();
        $callsLastMonth = Call::where('created_at', '>=', $lastMonth)
            ->count();
        $totalConsultasLastMonth = $whatsappLastMonth + $callsLastMonth;

        // Actividad hoy (últimas 24 horas)
        $last24Hours = Carbon::now()->subDay();
        $whatsappLast24h = Message::where('direction', 'inbound')
            ->where('created_at', '>=', $last24Hours)
            ->count();
        $callsLast24h = Call::where('created_at', '>=', $last24Hours)
            ->count();
        $totalConsultasLast24h = $whatsappLast24h + $callsLast24h;

        // Datos para gráfica de consultas por día (últimos 7 días)
        $consultasPorDia = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $dateEnd = Carbon::now()->subDays($i)->endOfDay();
            
            $whatsappCount = Message::where('direction', 'inbound')
                ->whereBetween('created_at', [$date, $dateEnd])
                ->count();
            $callsCount = Call::whereBetween('created_at', [$date, $dateEnd])
                ->count();
            
            $consultasPorDia[] = [
                'fecha' => $date->format('d/m'),
                'whatsapp' => $whatsappCount,
                'llamadas' => $callsCount,
                'total' => $whatsappCount + $callsCount,
            ];
        }

        // Datos para gráfica de distribución por canal (último mes)
        $distribucionCanal = [
            'whatsapp' => $whatsappLastMonth,
            'llamadas' => $callsLastMonth,
        ];

        return view('dashboard', [
            'user' => $user,
            'roles' => $roles,
            'totalConsultasLastMonth' => $totalConsultasLastMonth,
            'totalConsultasLast24h' => $totalConsultasLast24h,
            'consultasPorDia' => $consultasPorDia,
            'distribucionCanal' => $distribucionCanal,
        ]);
    }
}
