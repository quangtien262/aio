                        @php
                            $projectItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? []));
                            $normalizeProjectImages = function (array $project): \Illuminate\Support\Collection {
                                $rawImages = $project['gallery_images'] ?? $project['images'] ?? [];

                                if (is_string($rawImages)) {
                                    $rawImages = preg_split('/\r\n|\r|\n/', $rawImages) ?: [];
                                }

                                $images = collect(is_array($rawImages) ? $rawImages : [])
                                    ->map(function ($image) use ($project) {
                                        if (is_string($image)) {
                                            $url = trim($image);

                                            return $url !== '' ? ['image' => $url, 'alt' => $project['alt'] ?? $project['title'] ?? ''] : null;
                                        }

                                        if (! is_array($image)) {
                                            return null;
                                        }

                                        $url = trim((string) ($image['image'] ?? $image['image_url'] ?? $image['url'] ?? ''));

                                        return $url !== '' ? [
                                            'image' => $url,
                                            'alt' => $image['alt'] ?? $image['alt_text'] ?? $project['alt'] ?? $project['title'] ?? '',
                                            'caption' => $image['caption'] ?? '',
                                        ] : null;
                                    })
                                    ->filter()
                                    ->values();

                                if ($images->isEmpty() && filled($project['image'] ?? null)) {
                                    $images = collect([[
                                        'image' => $project['image'],
                                        'alt' => $project['alt'] ?? $project['title'] ?? '',
                                        'caption' => '',
                                    ]]);
                                }

                                return $images;
                            };
                        @endphp
                        <section id="{{ $anchor }}" class="xd-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container">
                                <div class="xd-section-title">
                                    <span class="xd-kicker">{{ $data['subtitle'] ?? 'Dự án' }}</span>
                                    <h2>{{ $data['title'] ?? '' }}</h2>
                                </div>
                            </div>
                            <div class="xd-row-slider xd-project-slider" data-row-slider>
                                <button class="xd-row-nav prev" type="button" data-row-prev aria-label="Dự án trước">&#8249;</button>
                                <div class="xd-projects xd-row-track" data-row-track>
                                    @foreach ($projectItems as $project)
                                        @php $projectImages = $normalizeProjectImages((array) $project); @endphp
                                        <article class="xd-project-card" data-project-gallery>
                                            <div class="xd-project-media">
                                                @foreach ($projectImages as $projectImage)
                                                    <img
                                                        class="xd-project-slide {{ $loop->first ? 'is-active' : '' }}"
                                                        src="{{ $projectImage['image'] ?? '' }}"
                                                        alt="{{ $projectImage['alt'] ?? $project['alt'] ?? $project['title'] ?? '' }}"
                                                        data-project-slide="{{ $loop->index }}"
                                                    >
                                                @endforeach
                                            </div>
                                            @if ($projectImages->count() > 1)
                                                <div class="xd-project-thumbs" aria-label="Chọn ảnh dự án">
                                                    @foreach ($projectImages as $projectImage)
                                                        <button
                                                            class="xd-project-thumb {{ $loop->first ? 'is-active' : '' }}"
                                                            type="button"
                                                            data-project-thumb="{{ $loop->index }}"
                                                            aria-label="Xem ảnh {{ $loop->iteration }} của {{ $project['title'] ?? 'dự án' }}"
                                                        >
                                                            <img src="{{ $projectImage['image'] ?? '' }}" alt="">
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <span class="xd-project-caption">
                                                <small>{{ $project['tag'] ?? $project['summary'] ?? '' }}</small>
                                                <a href="{{ $project['url'] ?? '#lien-he' }}">{{ $project['title'] ?? '' }}</a>
                                            </span>
                                        </article>
                                    @endforeach
                                </div>
                                <button class="xd-row-nav next" type="button" data-row-next aria-label="Dự án tiếp theo">&#8250;</button>
                                <div class="xd-row-dots" data-row-dots>
                                    @foreach ($projectItems as $project)
                                        <button class="xd-row-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-row-dot="{{ $loop->index }}" aria-label="Dự án {{ $loop->iteration }}"></button>
                                    @endforeach
                                </div>
                            </div>
                        </section>
