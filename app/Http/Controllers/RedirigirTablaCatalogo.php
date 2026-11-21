<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RedirigirTablaCatalogo
{
    public function __invoke(Request $request, string $tablaCatalogo, string $destinoCatalogo = 'admin.catalogos'): RedirectResponse
    {
        return redirect()->route($destinoCatalogo, ['tabla' => $tablaCatalogo] + $request->query());
    }
}
