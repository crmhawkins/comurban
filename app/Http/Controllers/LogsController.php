<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show logs
     */
    public function index(Request $request)
    {
        // Only allow admins
        if (!auth()->user()->hasAnyRole(['administrador', 'admin'])) {
            abort(403, 'No tienes permiso para ver los logs');
        }

        $logFile = storage_path('logs/laravel.log');
        $lines = $request->get('lines', 200); // Default 200 lines
        $filter = $request->get('filter', ''); // Filter by text

        $content = '';
        if (File::exists($logFile)) {
            $file = File::get($logFile);
            $allLines = explode("\n", $file);
            
            // Filter if needed
            if ($filter) {
                $allLines = array_filter($allLines, function($line) use ($filter) {
                    return stripos($line, $filter) !== false;
                });
            }
            
            // Get last N lines
            $allLines = array_slice($allLines, -$lines);
            $content = implode("\n", $allLines);
        }

        return view('logs.index', [
            'content' => $content,
            'lines' => $lines,
            'filter' => $filter,
            'file_exists' => File::exists($logFile),
            'file_size' => File::exists($logFile) ? File::size($logFile) : 0,
        ]);
    }

    /**
     * Clear logs
     */
    public function clear()
    {
        // Only allow admins
        if (!auth()->user()->hasAnyRole(['administrador', 'admin'])) {
            abort(403, 'No tienes permiso para limpiar los logs');
        }

        $logFile = storage_path('logs/laravel.log');
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }

        return back()->with('success', 'Logs limpiados correctamente');
    }

    /**
     * Test log
     */
    public function test()
    {
        Log::info('=== TEST LOG - ElevenLabs Webhook ===', [
            'timestamp' => now()->toDateTimeString(),
            'test' => true,
        ]);

        return back()->with('success', 'Log de prueba creado. Revisa los logs.');
    }
}
