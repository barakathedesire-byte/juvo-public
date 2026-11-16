@php
    $jvFooter = \App\Support\Homepage\FooterContent::forDisplay();
@endphp
<footer class="jv-footer">
    <div class="container jv-footer-top">
        <div class="row">
            <div class="col-12 col-md-4 mb-4">
                @include('themes.xylo.partials.logo', ['height' => 40])
                @if(!empty($jvFooter['description']))
                    <p style="font-size:13px;margin-top:14px;max-width:280px;">
                        {{ $jvFooter['description'] }}
                    </p>
                @endif

                @if(!empty($jvFooter['social_links']))
                    <div class="jv-social-ico mt-3">
                        @foreach($jvFooter['social_links'] as $jvSocial)
                            <a href="{{ $jvSocial['url'] }}" aria-label="{{ $jvSocial['label'] }}" title="{{ $jvSocial['label'] }}">
                                <i class="{{ $jvSocial['icon'] ?: 'fas fa-link' }}"></i>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if(!empty($jvFooter['newsletter']['enabled']))
                    <form id="newsletterForm" method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3" style="max-width:300px;">
                        @csrf
                        @if(!empty($jvFooter['newsletter']['label']))
                            <label class="d-block mb-1" style="font-size:13px;">{{ $jvFooter['newsletter']['label'] }}</label>
                        @endif
                        <div class="d-flex gap-1">
                            <input type="email" name="email" required
                                   placeholder="{{ $jvFooter['newsletter']['placeholder'] ?: 'Your email' }}"
                                   class="form-control form-control-sm" value="{{ old('email') }}">
                            <button class="jv-btn" type="submit" style="white-space:nowrap;">{{ $jvFooter['newsletter']['button'] ?: 'Subscribe' }}</button>
                        </div>
                        <input type="hidden" name="source" value="footer">
                    </form>
                @endif
            </div>

            @foreach($jvFooter['columns'] as $jvColumn)
                <div class="col-6 col-md-2 mb-4 jv-footer-col">
                    <h5>{{ $jvColumn['title'] }}</h5>
                    <ul>
                        @foreach($jvColumn['links'] as $jvLink)
                            <li><a href="{{ $jvLink['url'] }}">{{ $jvLink['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container">
        <div class="jv-footer-bottom">
            <span>{{ $jvFooter['copyright'] }}</span>
            <span>{{ $jvFooter['note'] }}</span>
        </div>
    </div>
</footer>
