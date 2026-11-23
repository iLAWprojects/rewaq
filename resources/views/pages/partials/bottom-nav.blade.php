@php
    $bnItems = [
        ['route' => 'libraries.index', 'icon' => 'library-big', 'label' => 'مكتباتي', 'match' => ['libraries.*']],
        ['route' => 'communities.index', 'icon' => 'users', 'label' => 'نوادي القراءة', 'match' => ['communities.*', 'challenges.*', 'calendar.*']],
        ['route' => 'explore', 'icon' => 'compass', 'label' => 'الكتب', 'match' => ['explore', 'books.*', 'search.*']],
        ['route' => 'notifications.index', 'icon' => 'bell', 'label' => 'الإشعارات', 'match' => ['notifications.*']],
        ['route' => 'settings.index', 'icon' => 'settings', 'label' => 'الإعدادات', 'match' => ['settings.*']],
    ];
    $bnActive = null;
    foreach ($bnItems as $i => $item) {
        if (request()->routeIs(...$item['match'])) {
            $bnActive = $i;
            break;
        }
    }
@endphp

<div class="bn {{ $bnActive === null ? 'bn-none' : '' }}" id="rq-bottom-nav" style="--i: {{ $bnActive ?? 0 }};">
    <div class="bn-bar" aria-hidden="true"></div>
    <span class="bn-dot" aria-hidden="true"></span>
    <div class="bn-items" role="navigation" aria-label="التنقل السفلي">
        @foreach($bnItems as $i => $item)
            <a href="{{ route($item['route']) }}" class="bn-item {{ $bnActive === $i ? 'on' : '' }}" @if($item['route'] === 'notifications.index') data-notif-open @endif @if($bnActive === $i) aria-current="page" @endif>
                <i data-lucide="{{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
                @if($item['route'] === 'notifications.index')
                    <b class="bn-badge" data-notif-count @if(($unreadNotificationsCount ?? 0) < 1) hidden @endif>{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</b>
                @endif
            </a>
        @endforeach
    </div>
</div>
