<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $banks = Bank::orderBy('name')->paginate(15);

        return view('banks.index', compact('banks'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        return view('banks.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:banks,code'],
            'ifsc_code' => ['nullable', 'string', 'max:11'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        Bank::create($validated);

        return redirect()->route('banks.index')->with('success', 'Bank added successfully.');
    }

    public function edit(Bank $bank): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        return view('banks.edit', compact('bank'));
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('banks', 'code')->ignore($bank->id)],
            'ifsc_code' => ['nullable', 'string', 'max:11'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $bank->update($validated);

        return redirect()->route('banks.index')->with('success', 'Bank updated successfully.');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $bank->delete();

        return redirect()->route('banks.index')->with('success', 'Bank deleted successfully.');
    }
}
