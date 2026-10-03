		<!--begin::Scrolltop-->
		<div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
			<i class="ki-duotone ki-arrow-up">
				<span class="path1"></span>
				<span class="path2"></span>
			</i>
		</div>
		<!--end::Scrolltop-->
		<audio id="beep" src="{{asset('dash/assets/media/noti.wav')}}"></audio>
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
			@elseif(session()->get("status") == 'error')
					toastr.options = {
					"positionClass": "toastr-top-left"
				};
				toastr.error("{{session()->get("message")}}");
			@elseif(session()->get("status") == 'success')
					toastr.options = {
					"positionClass": "toastr-top-left"
				};
				toastr.success("{{session()->get("message")}}");
			@endif
		</script>