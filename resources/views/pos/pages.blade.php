@if ($pages->hasPages())
    <nav class="pos-nav" aria-label="Pages">
        @if ($pages->previousPageUrl())
            <a href="{{ $pages->previousPageUrl() }}" rel="prev">Previous page</a>
        @endif
        @if ($pages->nextPageUrl())
            <a href="{{ $pages->nextPageUrl() }}" rel="next">Next page</a>
        @endif
    </nav>
@endif
