<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends RichContentController
{
    protected string $model = Service::class;

    protected string $view = 'services';

    protected string $label = 'Layanan';

    protected function parameter(): string
    {
        return 'service';
    }

    public function show(Service $service): View
    {
        return $this->showItem($service);
    }

    public function edit(Service $service): View
    {
        return $this->editItem($service);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        return $this->updateItem($request, $service);
    }

    public function destroy(Service $service): RedirectResponse
    {
        return $this->destroyItem($service);
    }
}
