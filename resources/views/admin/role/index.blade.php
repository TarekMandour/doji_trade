@extends('admin.layout.master')
@php
    $route = 'admin.roles';
    $viewPath = 'admin.role';
@endphp

@section('css')
    <link href="{{asset('dash/assets/plugins/custom/datatables/datatables.bundle.rtl.css')}}" rel="stylesheet" type="text/css" />
@endsection

@section('style')
    
@endsection

@section('breadcrumb')
    <!--begin::Toolbar-->
    <div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column me-3">
            <!--begin::Title-->
            <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.roles') }}</h1>
            <!--end::Title-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
                <!--begin::Item-->
                <li class="breadcrumb-item text-gray-600">
                    <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-gray-600">
                    <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.roles') }}</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-gray-600">{{ __('lang.list') }}</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
        <!--begin::Actions-->
        <div class="d-flex align-items-center py-2 py-md-1">

            <a href="{{route($route.'.create')}}" class="btn btn-dark fw-bold"><i class="ki-duotone ki-plus fs-2"></i> {{ __('lang.add_new') }}</a>

        </div>
        <!--end::Actions-->
    </div>
    <!--end::Toolbar-->
@endsection

@section('content')
    <!--begin::Container-->
    <div class="content flex-column-fluid" id="kt_content">
            
            <div class="card">
                <!--begin::Card header-->
                <div class="card-header border-0 pt-6">
                    <!--begin::Card title-->
                    <div class="card-title">
                        <!--begin::Search-->
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" data-kt-db-table-filter="search" id="search" class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('lang.search') }} ..." />
                        </div>
                        <!--end::Search-->
                    </div>
                    <!--begin::Card title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar">
                        <!--begin::Toolbar-->
                        <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                            <a href="javascript:;" class="btn btn-icon btn-danger me-2" id="btn_delete" data-token="{{ csrf_token() }}"><i class="bi bi-trash-fill fs-4"></i></a>
                        </div>
                        <!--end::Toolbar-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--begin::Card body-->
                <div class="card-body py-4">
                    <!--begin::Table-->
                    <div class="table-responsive">
                        <table class="table align-middle table-hover table-row-dashed text-start fs-6 gy-5" id="kt_table_list">
                            <thead>
                                <tr class="text-start text-dark bg-light-dark fw-bold fs-5 text-uppercase gs-0">
                                    <th class="text-start w-60px">#</th>
                                    <th class="text-start w-60px">
                                        <div class="form-check form-check-sm form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" data-kt-check="true" data-kt-check-target="#kt_table_list .form-check-input" value="1" />
                                        </div>
                                    </th>
                                    <th class="text-start">{{ __('lang.name') }}</th>
                                    <th class="text-start min-w-100px">{{ __('lang.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-800 fw-semibold">
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Card body-->

            </div>
    </div>
    <!--end::Container-->
@endsection

@section('script')
<script src="{{asset('dash/assets/plugins/custom/datatables/datatables.bundle.js')}}"></script>

<script>

    $(function () {
      
        var table = $('#kt_table_list').DataTable({
            processing: false,
            searching: false,
            serverSide: true,
            pageLength: 10,
            sort: false,
            language: {
                "loadingRecords": "{{ __('lang.loadingRecords') }}"
            },
            ajax: {
                url: "{{ route($route.'.index') }}",
                data: function (d) {
                    d.search = $('#search').val()
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'checkbox', name: 'checkbox'},
                {data: 'name', name: 'name'},
                {data: 'actions', name: 'actions'},
            ]
        });

        table.buttons().container().appendTo($('.dbuttons'));
        
        const filterSearch = document.querySelector('[data-kt-db-table-filter="search"]');
        filterSearch.addEventListener('keyup', function (e) {
            table.draw();
        });

        $("#btn_delete").click(function(event){
            event.preventDefault();
            var checkIDs = $("#kt_table_list input:checkbox:checked").map(function(){
            return $(this).val();
            }).get(); // <----

            if (checkIDs.length > 0) {
                var token = $(this).data("token");
                
                Swal.fire({
                    title: '{{ __("lang.confirm_delete") }}',
                    text: "{{ __('lang.cannot_restore') }}",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonClass: 'btn btn-success',
                    cancelButtonClass: 'btn btn-danger m-l-10',
                    confirmButtonText: '{{ __("lang.confirm") }}',
                    cancelButtonText: '{{ __("lang.cancel") }}'
                }).then(function (isConfirm) {
                    if (isConfirm.value) {
                        $.ajax(
                        {
                            url: "{{route($route.'.delete')}}",
                            type: 'post',
                            dataType: "JSON",
                            data: {
                                "id": checkIDs,
                                "_method": 'post',
                                "_token": token,
                            },
                            success: function (data) {
                                if(data.message == "success") {
                                    table.draw();
                                    toastr.success("", "{{ __('lang.deleted_successfully') }}");
                                } else {
                                    toastr.error("", "{{ __('lang.delete_failed') }}");
                                }
                            },
                            fail: function(xhrerrorThrown){
                                toastr.error("", "{{ __('lang.delete_failed') }}");
                            }
                        });
                    } else {
                        toastr.error("", "{{ __('lang.delete_cancelled') }}");
                    }
                });
            } else {
                toastr.error("", "{{ __('lang.select_items_first') }}");
            }        

        });
    });

    


</script>
@endsection