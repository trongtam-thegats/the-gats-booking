{{-- Bo loc chi nhanh dung chung. $branches, $branch, $allowAll (mac dinh: chi admin) --}}
@php($allowAll = $allowAll ?? auth()->user()->isAdmin())

@if ($branches->count() > 1 || $allowAll)
    <div class="field">
        <label for="branch">Chi nhánh</label>
        <select id="branch" name="branch" onchange="this.form.submit()">
            @if ($allowAll)
                <option value="">Tất cả chi nhánh</option>
            @endif
            @foreach ($branches as $option)
                <option value="{{ $option->id }}" @selected($branch && $branch->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </div>
@elseif ($branch)
    <input type="hidden" name="branch" value="{{ $branch->id }}">
@endif
