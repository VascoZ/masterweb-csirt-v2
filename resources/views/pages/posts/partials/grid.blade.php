@if ($posts->count())
    <div class="row g-4">
        @foreach ($posts as $post)
            <div class="col-lg-4 col-sm-6">
                <article class="card card-post card-hover h-100 position-relative">
                    <a href="{{ route('posts.index', ['category' => $post->category->slug]) }}" class="badge category-chip">{{ $post->category->name }}</a>
                    @if ($post->image)
                        <img src="{{ asset('storage/' . $post->image) }}" class="card-img-top" alt="" loading="lazy">
                    @else
                        <div class="card-img-placeholder"><i class="bi bi-shield-shaded"></i></div>
                    @endif
                    <div class="card-body d-flex flex-column">
                        <div class="small text-secondary mb-2">
                            {{ ($post->published_at ?? $post->created_at)->translatedFormat('d M Y') }} ·
                            <a href="{{ route('posts.index', ['author' => $post->author->username]) }}" class="text-secondary">{{ $post->author->name }}</a>
                        </div>
                        <h3 class="h5 card-title mb-2"><a href="{{ route('posts.show', $post) }}" class="stretched-link">{{ $post->title }}</a></h3>
                        <p class="card-text mb-0">{{ $post->excerpt }}</p>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@else
    <div class="empty-state"><i class="bi bi-newspaper"></i>Belum ada artikel.</div>
@endif
