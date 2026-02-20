<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class EmailAccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $accounts = EmailAccount::ordered()->paginate(20);

        return view('email-accounts.index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('email-accounts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mailer' => 'required|in:smtp,sendmail,mailgun,ses,postmark,array,log',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl,null',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // Si mailer es smtp, host y port son requeridos
        if ($validated['mailer'] === 'smtp') {
            $request->validate([
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
            ]);
        }

        $validated['active'] = $request->has('active');
        $validated['order'] = $validated['order'] ?? 0;
        $validated['encryption'] = $validated['encryption'] === 'null' ? null : ($validated['encryption'] ?? 'tls');
        $validated['from_address'] = $validated['from_address'] ?? $validated['email'];

        EmailAccount::create($validated);

        return redirect()->route('email-accounts.index')
            ->with('success', 'Cuenta de correo creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(EmailAccount $emailAccount)
    {
        return view('email-accounts.show', [
            'account' => $emailAccount,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmailAccount $emailAccount)
    {
        return view('email-accounts.edit', [
            'account' => $emailAccount,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmailAccount $emailAccount)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mailer' => 'required|in:smtp,sendmail,mailgun,ses,postmark,array,log',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl,null',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        // Si mailer es smtp, host y port son requeridos
        if ($validated['mailer'] === 'smtp') {
            $request->validate([
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
            ]);
        }

        $validated['active'] = $request->has('active');
        $validated['order'] = $validated['order'] ?? 0;
        $validated['encryption'] = $validated['encryption'] === 'null' ? null : ($validated['encryption'] ?? 'tls');
        $validated['from_address'] = $validated['from_address'] ?? $validated['email'];

        // Si no se proporciona password, mantener el actual
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $emailAccount->update($validated);

        return redirect()->route('email-accounts.index')
            ->with('success', 'Cuenta de correo actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmailAccount $emailAccount)
    {
        $emailAccount->delete();

        return redirect()->route('email-accounts.index')
            ->with('success', 'Cuenta de correo eliminada correctamente');
    }

    /**
     * Toggle active status
     */
    public function toggleActive(EmailAccount $emailAccount)
    {
        $emailAccount->update(['active' => !$emailAccount->active]);

        return back()->with('success', 'Estado de la cuenta actualizado correctamente');
    }

    /**
     * Test email account connection
     */
    public function test(EmailAccount $emailAccount)
    {
        try {
            // Configurar mail dinámicamente
            $mailConfig = $emailAccount->getMailConfig();
            Config::set('mail.mailers.test_account', $mailConfig);
            Config::set('mail.from', $mailConfig['from']);

            // Intentar enviar un correo de prueba
            Mail::mailer('test_account')->raw('Correo de prueba desde ' . config('app.name'), function ($message) use ($emailAccount) {
                $message->to($emailAccount->email)
                    ->subject('Prueba de configuración de correo');
            });

            return back()->with('success', 'Correo de prueba enviado correctamente a ' . $emailAccount->email);
        } catch (\Exception $e) {
            Log::error('Error testing email account', [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al enviar correo de prueba: ' . $e->getMessage());
        }
    }
}
