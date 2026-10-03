@extends('admin.layout.master')

@section('css')

@endsection

@section('style')
    
@endsection

@section('breadcrumb')
<!--begin::Toolbar-->
<div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
    <!--begin::Page title-->
    <div class="page-title d-flex flex-column me-3">
        <!--begin::Title-->
        <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{__('lang.settings')}}</h1>
        <!--end::Title-->
        <!--begin::Breadcrumb-->
        <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">
                <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{__('lang.dashboard')}}</a>
            </li>
            <!--end::Item-->
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">
                <a href="javascript:;" class="text-gray-600 text-hover-primary">{{__('lang.settings')}}</a>
            </li>
            <!--end::Item-->
            <!--begin::Item-->
            <li class="breadcrumb-item text-gray-600">{{__('lang.edit').' '.__('lang.data')}}</li>
            <!--end::Item-->
        </ul>
        <!--end::Breadcrumb-->
    </div>
    <!--end::Page title-->
</div>
<!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">
    <!--begin::About card-->
    <div class="card">
        <!--begin::Body-->
        <div class="card-body p-lg-17 text-center">
            <!--begin::Form-->
                <form action="{{route('admin.settings.update')}}" method="POST" enctype="multipart/form-data" id="kt_account_profile_details_form" class="form">
                    @csrf
                    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-__parent fs-4 fw-semibold mb-15">
                        <!--begin:::Tab item-->
                        <li class="nav-item">
                            <a class="nav-link text-dark text-active-primary pb-5 active" data-bs-toggle="tab" href="#setting">
                                <i class="las la-cog text-primary fs-3"></i>
                                {{__('lang.settings')}}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark text-active-primary pb-5" data-bs-toggle="tab" href="#media">
                                <i class="las la-image text-primary fs-3"></i>
                                {{__('lang.media')}}
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a class="nav-link text-dark text-active-primary pb-5" data-bs-toggle="tab" href="#mapsec">
                                <i class="las la-map text-primary fs-3"></i>
                                {{__('lang.map')}}
                            </a>
                        </li> --}}
                        <li class="nav-item">
                            <a class="nav-link text-dark text-active-primary pb-5" data-bs-toggle="tab" href="#social">
                                <i class="las la-hashtag text-primary fs-3"></i>
                                {{__('lang.social')}}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark text-active-primary pb-5" data-bs-toggle="tab" href="#description">
                                <i class="las la-comment text-primary fs-3"></i>
                                {{__('lang.seo')}}
                            </a>
                        </li>
                        <!--end:::Tab item-->
                    </ul>
                    <!--end:::Tabs-->
                    <!--begin:::Tab content-->
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="setting" role="tabpanel">
                            <!--begin::Form-->
                            <form action="{{route('admin.settings.update')}}" method="POST" class="form" >
                                @csrf

                                @foreach ($settings as $item)
                                    @if ($item->type == 'general')
                                        <div class="row fv-row mb-7">
                                            <div class="col-md-3 text-md-end">
                                                <label class="fs-6 fw-semibold form-label mt-3">
                                                    <span>{{__('lang.'.$item->key)}}</span>
                                                </label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" name="data[{{$item->key}}]" value="{{$item->value}}" class="form-control form-control-solid" />
                                            </div>
                                        </div>
                                    
                                    @endif
                                @endforeach
                                


                                <div class="row py-5">
                                    <div class="col-md-9 offset-md-3">
                                        <div class="d-flex">
                                            <button type="submit" data-kt-ecommerce-settings-type="submit" class="btn btn-primary">
                                                <span class="indicator-label">{{__('lang.save')}}</span>
                                                <span class="indicator-progress">Please wait...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Action buttons-->
                            </form>
                            <!--end::Form-->
                        </div>
                        <div class="tab-pane fade" id="media" role="tabpanel">

                            <!--begin::Form-->
                            <form action="{{route('admin.settings.update')}}" method="POST" class="form" enctype="multipart/form-data">
                                @csrf

                                @foreach ($settings as $item)
                                    @if ($item->type == 'media')
                                        <div class="row fv-row mb-7">
                                            <div class="col-md-3 text-md-end">
                                                <label class="fs-6 fw-semibold form-label mt-3">
                                                    <span>{{__('lang.'.$item->key)}}</span>
                                                </label>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="image-input image-input-outline" data-kt-image-input="true" style="background-image: url('assets/media/svg/avatars/blank.svg')">
                                                    @if ($item->getMedia($item->key)->count())
                                                    <div class="image-input-wrapper w-125px h-125px" style="background-image: url({{$item->getFirstMediaUrl($item->key)}})"></div>
                                                    @else
                                                    <div class="image-input-wrapper w-125px h-125px" style="background-image: url({{asset('dash/assets/media/svg/files/blank-image.svg')}})"></div>
                                                    @endif
                                                    <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="change" data-bs-toggle="tooltip" title="Change avatar">
                                                        <i class="bi bi-pencil-fill fs-7"></i>
                                                        <input type="file" name="{{$item->key}}" accept=".png, .jpg, .jpeg" />
                                                        <input type="hidden" name="{{$item->key}}_remove" />
                                                    </label>
                                                    <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="cancel" data-bs-toggle="tooltip" title="Cancel avatar">
                                                        <i class="bi bi-x fs-2"></i>
                                                    </span>
                                                    <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="Remove avatar">
                                                        <i class="bi bi-x fs-2"></i>
                                                    </span>
                                                </div>
                
                                            </div>
                                        </div>
                                    
                                    @endif
                                @endforeach
                                
                                <div class="row py-5">
                                    <div class="col-md-9 offset-md-3">
                                        <div class="d-flex">
                                            <button type="submit" data-kt-ecommerce-settings-type="submit" class="btn btn-primary">
                                                <span class="indicator-label">{{__('lang.save')}}</span>
                                                <span class="indicator-progress">Please wait...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Action buttons-->
                            </form>
                            <!--end::Form-->
                        </div>
                        <div class="tab-pane fade" id="social" role="tabpanel">
                            <!--begin::Form-->
                            <form action="{{route('admin.settings.update')}}" method="POST" class="form" enctype="multipart/form-data">
                                @csrf

                                @foreach ($settings as $item)
                                    @if ($item->type == 'social')
                                        <div class="row fv-row mb-7">
                                            <div class="col-md-3 text-md-end">
                                                <label class="fs-6 fw-semibold form-label mt-3">
                                                    <span>{{__('lang.'.$item->key)}}</span>
                                                </label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" name="data[{{$item->key}}]" value="{{$item->value}}" class="form-control form-control-solid" />
                                            </div>
                                        </div>
                                    
                                    @endif
                                @endforeach
                                


                                <div class="row py-5">
                                    <div class="col-md-9 offset-md-3">
                                        <div class="d-flex">
                                            <button type="submit" data-kt-ecommerce-settings-type="submit" class="btn btn-primary">
                                                <span class="indicator-label">{{__('lang.save')}}</span>
                                                <span class="indicator-progress">Please wait...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Action buttons-->
                            </form>
                            <!--end::Form-->
                        </div>

                        {{-- <div class="tab-pane fade" id="mapsec" role="tabpanel">
                            
                        </div> --}}
                        <div class="tab-pane fade" id="description" role="tabpanel">
                            <!--begin::Form-->
                            <form action="{{route('admin.settings.update')}}" method="POST" class="form" enctype="multipart/form-data">
                                @csrf

                                @foreach ($settings as $item)
                                    @if ($item->type == 'seo')
                                        <div class="row fv-row mb-7">
                                            <div class="col-md-3 text-md-end">
                                                <label class="fs-6 fw-semibold form-label mt-3">
                                                    <span>{{__('lang.'.$item->key)}}</span>
                                                </label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" name="data[{{$item->key}}]" value="{{$item->value}}" class="form-control form-control-solid" />
                                            </div>
                                        </div>
                                    
                                    @endif
                                @endforeach
                                


                                <div class="row py-5">
                                    <div class="col-md-9 offset-md-3">
                                        <div class="d-flex">
                                            <button type="submit" data-kt-ecommerce-settings-type="submit" class="btn btn-primary">
                                                <span class="indicator-label">{{__('lang.save')}}</span>
                                                <span class="indicator-progress">Please wait...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Action buttons-->
                            </form>
                            <!--end::Form-->
                        </div>
                    </div>

                </form>
                <!--end::Form-->
        
        </div>
        <!--end::Body-->
    </div>
    <!--end::About card-->
</div>


@endsection

@section('script')
<script
src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAsoJSU4k6RgH8tO2gM1WLZBjOFwUF4TcY&callback=initMap&v=weekly&language=ar"
defer
></script>
    <script>
        function initMap() {
            const myLatlng = { lat: 26.0879164, lng: 43.9878523 };
            const map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13.3,
                center: myLatlng,
            });
  
            // Create the initial InfoWindow.
            let infoWindow = new google.maps.InfoWindow({
                content: "حدد المكان على الخريطة",
                position: myLatlng,
            });

            infoWindow.open(map);
            // Configure the click listener.
            map.addListener("click", (mapsMouseEvent) => {
                // Close the current InfoWindow.
                infoWindow.close();
                // Create a new InfoWindow.
                infoWindow = new google.maps.InfoWindow({
                position: mapsMouseEvent.latLng,
                });
                infoWindow.setContent(
                // JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2),
                JSON.stringify('تم تحديد الموقع', null, 2),
                );
                $('#lat').val(mapsMouseEvent.latLng.toJSON().lat);
                $('#lng').val(mapsMouseEvent.latLng.toJSON().lng);
                infoWindow.open(map);
            });
        }

        window.initMap = initMap;
    </script>

<script src="{{ URL::asset('dash/assets/plugins/custom/tinymce/tinymce.bundle.js')}}"></script>

<script>
    var options = {selector: "#kt_docs_tinymce_basic"};

    tinymce.init(options);

</script>

@endsection