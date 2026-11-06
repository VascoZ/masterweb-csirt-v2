<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD bersama untuk konten berformat (Profil & Layanan): nama + isi Trix.
 */
abstract class RichContentController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $view;

    protected string $label;

    public function __construct()
    {
        $this->authorizeResource($this->model, $this->parameter());
    }

    abstract protected function parameter(): string;

    protected function extraRules(): array
    {
        return [];
    }

    public function index(): View
    {
        return view("dashboard.{$this->view}.index", ['items' => $this->model::latest()->get()]);
    }

    public function create(): View
    {
        return view("dashboard.{$this->view}.form", ['item' => new $this->model()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->model::create($this->validated($request));

        return redirect()->route("dashboard.{$this->view}.index")->with('success', "{$this->label} berhasil ditambahkan.");
    }

    protected function showItem(Model $item): View
    {
        return view("dashboard.{$this->view}.show", ['item' => $item]);
    }

    protected function editItem(Model $item): View
    {
        return view("dashboard.{$this->view}.form", ['item' => $item]);
    }

    protected function updateItem(Request $request, Model $item): RedirectResponse
    {
        $item->update($this->validated($request, $item));

        return redirect()->route("dashboard.{$this->view}.index")->with('success', "{$this->label} berhasil diperbarui.");
    }

    protected function destroyItem(Model $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route("dashboard.{$this->view}.index")->with('success', "{$this->label} berhasil dihapus.");
    }

    protected function validated(Request $request, ?Model $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($item?->getTable() ?? (new $this->model())->getTable())->ignore($item)],
            'content' => ['required', 'string'],
            ...$this->extraRules(),
        ]);

        $data['content'] = HtmlSanitizer::clean($data['content']);
        $data['slug'] = Str::slug($data['name']);

        return $data;
    }
}
