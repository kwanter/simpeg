<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class JabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:view jabatan')->only('index');
        $this->middleware('can:create jabatan')->only('create', 'store');
        $this->middleware('can:update jabatan')->only('edit', 'update');
        $this->middleware('can:delete jabatan')->only('destroy');
    }

    public function index()
    {
        $jabatans = Jabatan::all();
        return view('jabatan.index', compact('jabatans'));
    }

    public function create()
    {
        $jabatans = Jabatan::all();
        return view('jabatan.create', compact('jabatans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'parent_uuid' => 'nullable|exists:jabatan,uuid',
        ]);
        $validated['uuid'] = Str::uuid();
        Jabatan::create($validated);

        return redirect()->route('jabatan.index')->with('success', 'Jabatan created successfully.');
    }

    public function show(Jabatan $jabatan)
    {
        return view('jabatan.show', compact('jabatans'));
    }

    public function edit(Jabatan $jabatan)
    {
        $jabatans = Jabatan::all();
        return view('jabatan.edit', compact('jabatan', 'jabatans'));
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'parent_uuid' => 'nullable|exists:jabatan,uuid',
        ]);

        $jabatan->update($validated);

        return redirect()->route('jabatan.index')->with('success', 'Jabatan updated successfully.');
    }

    public function destroy(Jabatan $jabatan)
    {
        $jabatan->delete();

        return redirect()->route('jabatan.index')->with('success', 'Jabatan deleted successfully.');
    }
}
