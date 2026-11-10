<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index()
    {
        return view('tags.index', ['tags' => Tag::withCount('products')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Tag::create($this->validated($request));

        return back()->with('success', __('Tag saved.'));
    }

    public function update(Request $request, Tag $tag)
    {
        $tag->update($this->validated($request, $tag));

        return back()->with('success', __('Tag saved.'));
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return back()->with('success', __('Tag deleted.'));
    }

    private function validated(Request $request, ?Tag $tag = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('tags', 'name')->ignore($tag)],
            'color' => ['required', Rule::in(Tag::COLORS)],
        ]);
        $data['name'] = mb_strtolower(trim($data['name']));

        return $data;
    }
}
