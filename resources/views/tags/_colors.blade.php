<div class="flex flex-wrap gap-1.5">
    @foreach (\App\Models\Tag::COLORS as $color)
        <label class="cursor-pointer">
            <input type="radio" name="color" value="{{ $color }}" class="peer hidden" @checked($current === $color)>
            <x-tag :tag="new \App\Models\Tag(['name' => $color, 'color' => $color])" class="opacity-50 peer-checked:opacity-100 peer-checked:ring-2" />
        </label>
    @endforeach
</div>
