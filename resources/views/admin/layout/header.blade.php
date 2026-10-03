<div id="kt_header" class="header">
    <!--begin::Container-->
    <div class="container-fluid d-flex flex-stack">
        <!--begin::Brand-->
        <div class="d-flex align-items-center me-5">
            <!--begin::Aside toggle-->
            <div class="d-lg-none btn btn-icon btn-active-color-white w-30px h-30px ms-n2 me-3" id="kt_aside_toggle">
                <i class="ki-duotone ki-abstract-14 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </div>
            <!--end::Aside  toggle-->
            <!--begin::Logo-->
            <a href="{{url('/dashboard')}}">
                @if (settingMedia('logoDark'))
                <img alt="{{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}" src="{{settingMedia('logoDark')}}" class="h-25px h-lg-50px" />
                @else
                {{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}
                @endif
            </a>
            <!--end::Logo-->
            <!--begin::Nav-->
            <div class="ms-5 ms-md-10">
                
            </div>
            <!--end::Nav-->
        </div>
        <!--end::Brand-->
        <!--begin::Topbar-->
        <div class="d-flex align-items-center flex-shrink-0">

            <div class="d-flex align-items-center ms-1">
                <!--begin::Menu toggle-->
                <a href="#" class="btn btn-icon btn-color-white bg-hover-white bg-hover-opacity-10 w-30px h-30px h-40px w-40px" data-kt-menu-trigger="{default:'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                    <i class="ki-duotone ki-night-day theme-light-show fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                        <span class="path6"></span>
                        <span class="path7"></span>
                        <span class="path8"></span>
                        <span class="path9"></span>
                        <span class="path10"></span>
                    </i>
                    <i class="ki-duotone ki-moon theme-dark-show fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </a>
                <!--begin::Menu toggle-->
                <!--begin::Menu-->
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true" data-kt-element="theme-mode-menu">
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-night-day fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                    <span class="path6"></span>
                                    <span class="path7"></span>
                                    <span class="path8"></span>
                                    <span class="path9"></span>
                                    <span class="path10"></span>
                                </i>
                            </span>
                            <span class="menu-title">Light</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-moon fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Dark</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-screen fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </span>
                            <span class="menu-title">System</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                </div>
                <!--end::Menu-->
            </div>
            <!--end::Theme mode-->
            <!--begin::User-->
            <div class="d-flex align-items-center ms-1" id="kt_header_user_menu_toggle">
                <!--begin::User info-->
                <div class="btn btn-flex align-items-center bg-hover-white bg-hover-opacity-10 py-2 px-2 px-md-3" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                    <!--begin::Name-->
                    <div class="d-none d-md-flex flex-column align-items-end justify-content-center me-2 me-md-4">
                        <span class="text-muted fs-8 fw-semibold lh-1 mb-1">{{Auth::guard('admin')->user()->name}}</span>
                        <span class="text-white fs-8 fw-bold lh-1">{{Auth::guard('admin')->user()->email}}</span>
                    </div>
                    <!--end::Name-->
                    <!--begin::Symbol-->
                    <div class="symbol symbol-30px symbol-md-40px">
                        @if(Auth::guard('admin')->user()->getMedia('profile')->count())
                            <img src="{{Auth::guard('admin')->user()->getFirstMediaUrl('profile')}}" alt="{{Auth::guard('admin')->user()->name}}" />
                        @else
                            <img alt="{{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}" src="{{asset('dash/assets/media/avatars/blank.png')}}" />
                        @endif
                        
                    </div>
                    <!--end::Symbol-->
                </div>
                <!--end::User info-->
                <!--begin::User account menu-->
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                    <!--begin::Menu item-->
                    <div class="menu-item px-3">
                        <div class="menu-content d-flex align-items-center px-3">
                            <!--begin::Avatar-->
                            <div class="symbol symbol-50px me-5">
                                @if(Auth::guard('admin')->user()->getMedia('profile')->count())
                                    <img src="{{Auth::guard('admin')->user()->getFirstMediaUrl('profile')}}" alt="{{Auth::guard('admin')->user()->name}}" />
                                @else
                                    <img alt="{{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}" src="{{asset('dash/assets/media/avatars/blank.png')}}" />
                                @endif
                        
                            </div>
                            <!--end::Avatar-->
                            <!--begin::Username-->
                            <div class="d-flex flex-column">
                                <div class="fw-bold d-flex align-items-center fs-5">{{Auth::guard('admin')->user()->name}}</div>
                                <a href="javascript:;" class="fw-semibold text-muted text-hover-primary fs-7">{{Auth::guard('admin')->user()->email}}</a>
                            </div>
                            <!--end::Username-->
                        </div>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu separator-->
                    <div class="separator my-2"></div>
                    <!--end::Menu separator-->
                    <!--begin::Menu item-->
                    <div class="menu-item px-5">
                        <a href="{{route('admin.admins.show', Auth::guard('admin')->id())}}" class="menu-link px-5">{{__('auth.profile')}}</a>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu separator-->
                    <div class="separator my-2"></div>
                    <!--end::Menu separator-->
                    
                    <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                        <a href="#" class="menu-link px-5">
                            <span class="menu-title position-relative">{{__('lang.lang')}}
                                <span class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">
                                    @if (!isArabic())
                                        <span class="symbol symbol-20px me-4">
                                            <img class="rounded-1" src="{{asset('dash/assets/media/flags/united-kingdom.svg')}}" alt="" />
                                        </span>
                                        {{__('lang.english')}}
                                    @elseif (isArabic())
                                        <span class="symbol symbol-20px me-4">
                                            <img class="rounded-1" src="{{asset('dash/assets/media/flags/saudi-arabia.svg')}}" alt="" />
                                        </span>
                                        {{__('lang.arabic')}}
                                    @endif
                                </span>
                            </span>
                        </a>

                        <!--begin::Menu sub-->
                        <div class="menu-sub menu-sub-dropdown w-175px py-4">
                            <div class="menu-item px-3">
                                <a href="{{ localeUrl('en') }}" class="menu-link d-flex px-5">
                                <span class="symbol symbol-20px me-4">
                                    <img class="rounded-1" src="{{asset('dash/assets/media/flags/united-kingdom.svg')}}" alt="" />
                                </span>{{__('lang.english')}}</a>
                            </div> 
                            <div class="menu-item px-3">
                                <a href="{{ localeUrl('ar') }}" class="menu-link d-flex px-5">
                                <span class="symbol symbol-20px me-4">
                                    <img class="rounded-1" src="{{asset('dash/assets/media/flags/saudi-arabia.svg')}}" alt="" />
                                </span>{{__('lang.arabic')}}</a>
                            </div>
    
                        </div>
                        <!--end::Menu sub-->

                    </div>

                    <!--begin::Menu item-->
                    <div class="menu-item px-5">
                        <a href="{{route('admin.logout', ['locale' => app()->getLocale()])}}" class="menu-link px-5">{{ __('lang.logout') }}</a>
                    </div>
                    <!--end::Menu item-->
                </div>
                <!--end::User account menu-->
            </div>
            <!--end::User -->
        </div>
        <!--end::Topbar-->
    </div>
    <!--end::Container-->
</div>