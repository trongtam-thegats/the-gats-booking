@if ($paginator->hasPages())
    <nav class="row" style="gap:6px">
        @if ($paginator->onFirstPage())
            <span class="btn btn-ghost btn-sm" style="opacity:.4">← Trước</span>
        @else
            <a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Trước</a>
        @endif

        <span class="muted small" style="padding:0 6px">
            Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            &middot; {{ $paginator->total() }} bản ghi
        </span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau →</a>
        @else
            <span class="btn btn-ghost btn-sm" style="opacity:.4">Sau →</span>
        @endif
    </nav>
@endif
