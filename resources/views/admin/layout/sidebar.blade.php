<div id="kt_aside" class="aside card" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
    <!--begin::Aside menu-->
    <div class="aside-menu flex-column-fluid px-4">
        <!--begin::Aside Menu-->
        <div class="hover-scroll-overlay-y mh-100 my-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="{default: '#kt_aside_footer', lg: '#kt_header, #kt_aside_footer'}" data-kt-scroll-wrappers="#kt_aside, #kt_aside_menu" data-kt-scroll-offset="{default: '5px', lg: '75px'}">
            <!--begin::Menu-->
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-5" id="#kt_aside_menu" data-kt-menu="true">

                {{-- Dashboard --}}
                <div class="menu-item pt-5">
                    <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{route('admin.dashboard')}}">
                        <span class="menu-icon"><i class="bi bi-house-fill"></i></span>
                        <span class="menu-title">{{ __('lang.home') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>


                {{-- ── User Management ────────────────────────────────────── --}}
                @canany(['users view', 'students view', 'degrees view', 'buses view', 'student-reviews view'])
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold fs-5">{{ __('lang.students_management') }}</span>
                        </div>
                    </div>

                    @can('users view')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{route('admin.users.index')}}">
                            <span class="menu-icon"><i class="bi bi-people-fill"></i></span>
                            <span class="menu-title">{{ __('lang.users') }}</span>
                            <span class="menu-arrow"></span>
                        </a>
                    </div>
                    @endcan

                @endcanany

                {{-- ── Settings Management ────────────────────────────────────── --}}
                @canany(['admins view', 'levels view', 'subjects view', 'sections view', 'notifications view', 'pages view', 'scratchvideos view', 'roles view', 'stocks view', 'watchlists view', 'analysis view'])
                <div class="menu-item pt-5">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold fs-5">{{ __('lang.settings_management') }}</span>
                    </div>
                </div>

                @can('admins view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}" href="{{route('admin.admins.index')}}">
                        <span class="menu-icon"><i class="bi bi-shield-fill-check"></i></span>
                        <span class="menu-title">{{ __('lang.admins') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('roles view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{route('admin.roles.index')}}">
                        <span class="menu-icon"><i class="bi bi-key-fill"></i></span>
                        <span class="menu-title">{{ __('lang.roles') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('notifications view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{route('admin.notifications.index')}}">
                        <span class="menu-icon"><i class="bi bi-bell-fill"></i></span>
                        <span class="menu-title">{{ __('lang.notifications') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('pages view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}" href="{{route('admin.pages.index')}}">
                        <span class="menu-icon"><i class="bi bi-file-earmark-text-fill"></i></span>
                        <span class="menu-title">{{ __('lang.pages') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('stocks view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.stocks.*') ? 'active' : '' }}" href="{{route('admin.stocks.index')}}">
                        <span class="menu-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="menu-title">{{ __('lang.stocks') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('watchlists view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.watchlists.*') ? 'active' : '' }}" href="{{route('admin.watchlists.index')}}">
                        <span class="menu-icon"><i class="bi bi-star-fill"></i></span>
                        <span class="menu-title">{{ __('lang.watchlists') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @can('analysis view')
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.analysis.*') ? 'active' : '' }}" href="{{route('admin.analysis.index')}}">
                        <span class="menu-icon"><i class="bi bi-graph-up"></i></span>
                        <span class="menu-title">{{ __('lang.analysis') }}</span>
                        <span class="menu-arrow"></span>
                    </a>
                </div>
                @endcan

                @endcanany

            </div>
            <!--end::Menu-->
        </div>
    </div>
    <!--end::Aside menu-->
    <!--begin::Footer-->

    <!--end::Footer-->
</div>