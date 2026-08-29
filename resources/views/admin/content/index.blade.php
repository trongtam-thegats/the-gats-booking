@extends('layouts.admin')

@section('title', 'Nội dung trang đặt bàn')

@php
    $rowFor = fn (string $key, string $code) => $brand->contents
        ->first(fn ($row) => $row->key === $key && $row->locale === $code);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Nội dung trang đặt bàn</h1>
            <p>Sửa chữ khách nhìn thấy. Ô nào bỏ trống thì dùng nội dung mặc định của hệ thống.</p>
        </div>
        <a class="btn btn-ghost" target="_blank"
           href="{{ url('/?brand='.$brand->slug.'&lang='.$locale) }}">Xem thử trang khách</a>
    </div>

    <form method="get" class="filters">
        @if ($brands->count() > 1)
            <div class="field">
                <label for="quan">Quán</label>
                <select id="quan" name="quan" onchange="this.form.submit()">
                    @foreach ($brands as $option)
                        <option value="{{ $option->id }}" @selected($option->is($brand))>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label for="ngon-ngu">Ngôn ngữ</label>
            <select id="ngon-ngu" name="ngon-ngu" onchange="this.form.submit()">
                @foreach (\App\Support\Locales::ALL as $code => [$name, $short])
                    <option value="{{ $code }}" @selected($locale === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @php
        $otherLocale = collect(\App\Support\Locales::codes())->first(fn ($c) => $c !== $locale);
        $missing = collect(\App\Models\Brand::TEXTS)
            ->keys()
            ->filter(fn ($key) => $rowFor($key, $otherLocale) && ! $rowFor($key, $locale));
    @endphp

    @if ($missing->isNotEmpty())
        <div class="alert alert-info">
            Bản {{ \App\Support\Locales::label($otherLocale) }} đã sửa {{ $missing->count() }} mục mà bản
            {{ \App\Support\Locales::label($locale) }} chưa có. Những mục đó sẽ hiện câu mặc định của hệ thống,
            <b>không</b> lấy chữ từ ngôn ngữ kia.
        </div>
    @endif

    <form method="post" action="{{ route('admin.content.update', $brand) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="locale" value="{{ $locale }}">

        <div class="card">
            <h2>Ảnh bìa</h2>
            <p class="sub">
                Dùng chung cho cả hai ngôn ngữ. Ảnh ngang, nên rộng ít nhất 1200px. Tối đa 3MB.
                Hệ thống tự thu nhỏ và nén lại để trang không bị nặng trên điện thoại.
            </p>

            @if ($brand->hasCover())
                <img src="{{ asset($brand->cover_path) }}" alt="Ảnh bìa {{ $brand->name }}"
                     style="width:100%; max-width:460px; border-radius:10px; display:block; margin-bottom:10px">
                <label class="check">
                    <input type="checkbox" name="remove_cover" value="1"> Gỡ ảnh bìa hiện tại
                </label>
            @endif

            <input type="file" name="cover" accept="image/*" style="margin-top:10px">
        </div>

        <div class="card">
            <h2>Chữ trên trang — {{ \App\Support\Locales::label($locale) }}</h2>
            <p class="sub">Chữ xám mờ trong ô là nội dung mặc định đang dùng.</p>

            <div class="form-grid">
                @foreach (\App\Models\Brand::TEXTS as $key => [$label, $defaultKey, $type, $hint])
                    @php($row = $rowFor($key, $locale))
                    @php($default = $defaultKey ? __($defaultKey, [], $locale) : '')

                    <div class="field {{ $type === 'textarea' ? 'full' : '' }}">
                        <label for="text-{{ $key }}">{{ $label }}</label>

                        @if ($type === 'textarea')
                            <textarea id="text-{{ $key }}" name="texts[{{ $key }}]" maxlength="1000"
                                      placeholder="{{ $default ?: 'Bỏ trống nếu không muốn hiện' }}">{{ $row?->value }}</textarea>
                        @else
                            <input type="text" id="text-{{ $key }}" name="texts[{{ $key }}]" maxlength="160"
                                   value="{{ $row?->value }}" placeholder="{{ $default }}">
                        @endif

                        <span class="hint">{{ $hint }}</span>
                    </div>
                @endforeach
            </div>

            <button class="btn" type="submit" style="margin-top:18px">
                Lưu nội dung {{ \App\Support\Locales::label($locale) }}
            </button>
        </div>
    </form>
@endsection
