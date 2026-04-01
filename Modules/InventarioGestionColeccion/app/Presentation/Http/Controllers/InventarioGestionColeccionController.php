<?php

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InventarioGestionColeccionController extends Controller
{
    public function index()
    {
        return view('inventariogestioncoleccion::index');
    }

    public function create()
    {
        return view('inventariogestioncoleccion::create');
    }

    public function store(Request $request) {}

    public function show($id)
    {
        return view('inventariogestioncoleccion::show');
    }

    public function edit($id)
    {
        return view('inventariogestioncoleccion::edit');
    }

    public function update(Request $request, $id) {}

    public function destroy($id) {}
}
