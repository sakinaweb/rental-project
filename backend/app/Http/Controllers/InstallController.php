<?php

namespace App\Http\Controllers;

use App\Support\Installer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// /install: one page, no session (the database may not exist yet), gone once installed.
class InstallController extends Controller
{
    public function show(Request $request): View
    {
        abort_if(Installer::installed(), 404);

        return view('install', ['requirements' => Installer::requirements(), 'old' => [], 'problems' => [], 'url' => $request->root()]);
    }

    public function store(Request $request, Installer $installer): View|RedirectResponse
    {
        abort_if(Installer::installed(), 404);

        $validator = Validator::make($request->all(), [
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $errors = $validator->errors()->all();
        if (! in_array(false, Installer::requirements(), true) && ! $errors) {
            try {
                $installer->install($validator->validated() + ['redis' => $request->boolean('redis'), 'demo' => $request->boolean('demo')]);

                return redirect('/admin');
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'Installation failed: '.$e->getMessage();
            }
        }

        return view('install', [
            'requirements' => Installer::requirements(),
            'old' => $request->except(['admin_password', 'admin_password_confirmation', 'db_password']),
            'problems' => $errors,
            'url' => $request->root(),
        ]);
    }
}
