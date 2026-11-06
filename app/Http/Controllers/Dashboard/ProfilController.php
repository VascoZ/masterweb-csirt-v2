<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Profil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilController extends RichContentController
{
    protected string $model = Profil::class;

    protected string $view = 'profils';

    protected string $label = 'Profil';

    protected function parameter(): string
    {
        return 'profil';
    }

    public function show(Profil $profil): View
    {
        return $this->showItem($profil);
    }

    public function edit(Profil $profil): View
    {
        return $this->editItem($profil);
    }

    public function update(Request $request, Profil $profil): RedirectResponse
    {
        return $this->updateItem($request, $profil);
    }

    public function destroy(Profil $profil): RedirectResponse
    {
        return $this->destroyItem($profil);
    }
}
