<!DOCTYPE html>
<html @if (App::getLocale() == 'ar') lang="ar" direction="rtl" dir="rtl" style="direction: rtl;" @else lang="en" @endif>
	<!--begin::Head-->
	<head><base href="{{url('/'.app()->getLocale().'/admin')}}"/>
		<title>{{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}</title>
		<meta charset="utf-8" />
		<meta name="description" content="{{(App::getLocale() == 'ar') ? setting('description_ar') : setting('description_en')}}" />
		<meta name="keywords" content="{{(App::getLocale() == 'ar') ? setting('keywords_ar') : setting('keywords_en')}}" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<link rel="shortcut icon" href="{{settingMedia('fav')}}" />
		
        <!--begin::Fonts(mandatory for all pages)-->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">

        <!--end::Fonts--> 
        <!--begin::Vendor Stylesheets(used for this page only)-->
        @yield('css')
        <!--end::Vendor Stylesheets-->
        <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
        @if (App::getLocale() == 'ar')
            <link href="{{asset('dash/assets/plugins/global/plugins.bundle.rtl.css')}}" rel="stylesheet" type="text/css" />
            <link href="{{asset('dash/assets/css/style.bundle.rtl.css')}}" rel="stylesheet" type="text/css" />
        @else
            <link href="{{asset('dash/assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
            <link href="{{asset('dash/assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
            {{-- <link href="{{asset('dash/assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
            <link href="{{asset('dash/assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" /> --}}
        @endif
        <!--end::Global Stylesheets Bundle-->
        
	</head> 
	<!--end::Head-->
	<!--begin::Body-->
	<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed">
		<div class="d-flex flex-column flex-root">
			<!--begin::Page bg image-->
			<style>body { background-image: url({{asset('dash/assets/media/auth/bg10.jpeg')}}); } [data-bs-theme="dark"] body { background-image: url({{asset('dash/assets/media/auth/bg10-dark.jpeg')}}); }</style>
			<!--end::Page bg image-->
			<!--begin::Authentication - Sign-in -->
			<div class="d-flex flex-column flex-lg-row flex-column-fluid">
				<!--begin::Aside-->
				<div class="d-flex flex-lg-row-fluid">
					<!--begin::Content-->
					<div class="d-flex flex-column flex-center pb-0 pb-lg-10 p-10 w-100">
						<!--begin::Image-->
                        @if (settingMedia('logo'))
						<img class="theme-light-show mx-auto mw-100 w-150px w-lg-300px mb-10 mb-lg-20" src="{{settingMedia('logo')}}" alt="{{setting('name_en')}}" />
						<img class="theme-dark-show mx-auto mw-100 w-150px w-lg-300px mb-10 mb-lg-20" src="{{settingMedia('logoDark')}}" alt="{{setting('name_en')}}" />
						@else
                        {{setting('name_en')}}
                        @endif
                        <!--end::Image-->
					</div>
					<!--end::Content-->
				</div>
				<!--begin::Aside-->
				<!--begin::Body-->
				<div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12">
					<!--begin::Wrapper-->
					<div class="bg-body d-flex flex-column flex-center rounded-4 w-md-600px p-10">
						<!--begin::Content-->
						<div class="d-flex flex-center flex-column align-items-stretch h-lg-100 w-md-400px">
							<!--begin::Wrapper-->
							<div class="d-flex flex-center flex-column flex-column-fluid pb-15 pb-lg-20">
								<!--begin::Form-->
								<form class="form w-100" novalidate="novalidate" action="{{route('admin.login.submit')}}" method="POST" id="kt_sign_in_form">
									@csrf
                                    <!--begin::Heading-->
									<div class="text-center mb-11">
										<!--begin::Title-->
										<h1 class="text-gray-900 fw-bolder mb-3">{{ __('lang.login_title') }}</h1>
										<!--end::Title-->
									</div>
									<!--begin::Heading-->

									<!--begin::Input group=-->
									<div class="fv-row mb-8">
										<!--begin::Email-->
											<input type="text" placeholder="{{ __('lang.email') }}" name="email" autocomplete="off" class="form-control bg-transparent" />
										<!--end::Email-->
									</div>
									<!--end::Input group=-->
									<div class="fv-row mb-3">
										<!--begin::Password-->
											<input type="password" placeholder="{{ __('lang.password') }}" name="password" autocomplete="off" class="form-control bg-transparent" />
										<!--end::Password-->
									</div>
									<!--end::Input group=-->

									<!--begin::Submit button-->
									<div class="d-grid mb-10">
										<button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
											<!--begin::Indicator label-->
													<span class="indicator-label">{{ __('lang.login_title') }}</span>
											<!--end::Indicator label-->
											<!--begin::Indicator progress-->
											<span class="indicator-progress">Please wait... 
											<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
											<!--end::Indicator progress-->
										</button>
									</div>
									<!--end::Submit button-->
								</form>
								<!--end::Form-->
							</div>
							<!--end::Wrapper-->
						</div>
						<!--end::Content-->
					</div>
					<!--end::Wrapper-->
				</div>
				<!--end::Body-->
			</div>
			<!--end::Authentication - Sign-in-->
		</div>
        
        <!--begin::Javascript-->
		<script>var hostUrl = "assets/";</script>
		<!--begin::Global Javascript Bundle(mandatory for all pages)-->
		<script src="{{asset('dash/assets/plugins/global/plugins.bundle.js')}}"></script>
		<script src="{{asset('dash/assets/js/scripts.bundle.js')}}"></script>
		<!--end::Global Javascript Bundle-->
		<!--begin::Custom Javascript(used for this page only)-->
		@yield('script')
		<!--end::Custom Javascript-->
		<!--end::Javascript-->

		<script>
			@if ($errors->any())
				@foreach ($errors->all() as $error)
				toastr.options = {
				"positionClass": "toastr-top-left"
				};
				toastr.error("{{ $error }}");
				@endforeach
			@elseif(session()->has("message"))
					toastr.options = {
					"positionClass": "toastr-top-left"
				};
				toastr.success("{{session()->get("message")}}");
			@endif
		</script>
	</body>
	<!--end::Body-->
</html>