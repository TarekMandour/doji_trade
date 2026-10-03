@extends('admin.layout.master')

@php
    $route = 'admin.notifications';
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
                <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.notifications') }}</h1>
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
                        <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.notifications') }}</a>
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
                <!--begin::Wrapper-->
                <div class="me-3">
                    <!--begin::Menu-->
                    <a href="#" class="btn btn-light fw-bold" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <i class="ki-duotone ki-filter fs-5 text-gray-500 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>{{__('lang.filter')}}</a>
                    <!--begin::Menu 1-->
                    <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px" data-kt-menu="true" id="kt_menu_685c37c8c6d74">
                        <!--begin::Header-->
                        <div class="px-7 py-5">
                            <div class="fs-5 text-gray-900 fw-bold">{{__('lang.filter')}}</div>
                        </div>
                        <!--end::Header-->
                        <!--begin::Menu separator-->
                        <div class="separator border-gray-200"></div>
                        <!--end::Menu separator-->
                        <!--begin::Form-->
                        <div class="px-7 py-5">
                            <form id="filter-form">
                            <!--begin::Input group-->

                            <div class="mb-7">
                                <label class="required fw-semibold">{{__('lang.created_at')}}  </label>
                                <div class="position-relative d-flex align-items-center">
                                    <span class="svg-icon svg-icon-2 position-absolute mx-4">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor" />
                                            <path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor" />
                                            <path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor" />
                                        </svg>
                                    </span>
                                    <input class="form-control form-control-solid ps-12" name="from_date" placeholder="{{__('lang.from')}}" id="kt_datepicker_1" />
                                </div>
                            </div>

                            <div class="mb-7">
                                <div class="position-relative d-flex align-items-center">
                                    <span class="svg-icon svg-icon-2 position-absolute mx-4">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor" />
                                            <path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor" />
                                            <path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.59999 10.8 8.39999 10.9C8.19999 11 8.1 11.1 8 11.2C7.9 11.3 7.8 11.4 7.7 11.6C7.6 11.8 7.5 11.9 7.5 12.1C7.5 12.2 7.4 12.2 7.3 12.3C7.2 12.4 7.09999 12.4 6.89999 12.4C6.69999 12.4 6.6 12.3 6.5 12.2C6.4 12.1 6.3 11.9 6.3 11.7C6.3 11.5 6.4 11.3 6.5 11.1C6.6 10.9 6.8 10.7 7 10.5C7.2 10.3 7.49999 10.1 7.89999 10C8.29999 9.90003 8.60001 9.80003 9.10001 9.80003C9.50001 9.80003 9.80001 9.90003 10.1 10C10.4 10.1 10.7 10.3 10.9 10.4C11.1 10.5 11.3 10.8 11.4 11.1C11.5 11.4 11.6 11.6 11.6 11.9C11.6 12.3 11.5 12.6 11.3 12.9C11.1 13.2 10.9 13.5 10.6 13.7C10.9 13.9 11.2 14.1 11.4 14.3C11.6 14.5 11.8 14.7 11.9 15C12 15.3 12.1 15.5 12.1 15.8C12.1 16.2 12 16.5 11.9 16.8C11.8 17.1 11.5 17.4 11.3 17.7C11.1 18 10.7 18.2 10.3 18.3C9.9 18.4 9.5 18.5 9 18.5C8.5 18.5 8.1 18.4 7.7 18.2C7.3 18 7 17.8 6.8 17.6C6.6 17.4 6.4 17.1 6.3 16.8C6.2 16.5 6.10001 16.3 6.10001 16.1C6.10001 15.9 6.2 15.7 6.3 15.6C6.4 15.5 6.6 15.4 6.8 15.4C6.9 15.4 7.00001 15.4 7.10001 15.5C7.20001 15.6 7.3 15.6 7.3 15.7C7.5 16.2 7.7 16.6 8 16.9C8.3 17.2 8.6 17.3 9 17.3C9.2 17.3 9.5 17.2 9.7 17.1C9.9 17 10.1 16.8 10.3 16.6C10.5 16.4 10.5 16.1 10.5 15.8C10.5 15.3 10.4 15 10.1 14.7C9.80001 14.4 9.50001 14.3 9.10001 14.3C9.00001 14.3 8.9 14.3 8.7 14.3C8.5 14.3 8.39999 14.3 8.39999 14.3C8.19999 14.3 7.99999 14.2 7.89999 14.1C7.79999 14 7.7 13.8 7.7 13.7C7.7 13.5 7.79999 13.4 7.89999 13.2C7.99999 13 8.2 13 8.5 13H8.8V13.1ZM15.3 17.5V12.2C14.3 13 13.6 13.3 13.3 13.3C13.1 13.3 13 13.2 12.9 13.1C12.8 13 12.7 12.8 12.7 12.6C12.7 12.4 12.8 12.3 12.9 12.2C13 12.1 13.2 12 13.6 11.8C14.1 11.6 14.5 11.3 14.7 11.1C14.9 10.9 15.2 10.6 15.5 10.3C15.8 10 15.9 9.80003 15.9 9.70003C15.9 9.60003 16.1 9.60004 16.3 9.60004C16.5 9.60004 16.7 9.70003 16.8 9.80003C16.9 9.90003 17 10.2 17 10.5V17.2C17 18 16.7 18.4 16.2 18.4C16 18.4 15.8 18.3 15.6 18.2C15.4 18.1 15.3 17.8 15.3 17.5Z" fill="currentColor" />
                                        </svg>
                                    </span>
                                    <input class="form-control form-control-solid ps-12" name="to_date" placeholder="{{__('lang.to')}}" id="kt_datepicker_2" />
                                </div>
                            </div>

                            <!--end::Input group-->

                            <!--begin::Actions-->
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-sm btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">{{__('lang.cancel')}}</button>
                                <button type="submit" class="btn btn-sm btn-primary" data-kt-menu-dismiss="true">{{__('lang.save')}}</button>
                            </div>
                            <!--end::Actions-->
                            </form>
                        </div>
                        <!--end::Form-->
                    </div>
                    <!--end::Menu 1-->
                    <!--end::Menu-->
                </div>
                <!--end::Wrapper-->

                <a href="{{route($route.'.create')}}" class="btn btn-dark fw-bold"><i class="ki-duotone ki-plus fs-2"></i> {{__('lang.add_new')}}</a>


            </div>
            <!--end::Actions-->
        </div>
        <!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">
    <!--begin::Card-->
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
                    <input type="text" data-kt-db-table-filter="search" id="search" class="form-control form-control-solid w-250px ps-13" placeholder="{{__('lang.search')}} ..." />
                </div>
                <!--end::Search-->
            </div>
            <!--begin::Card title-->
            <!--begin::Card toolbar-->
            <div class="card-toolbar">
                <!--begin::Toolbar-->
                <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                    <a href="javascript:;" class="btn btn-icon btn-success me-2" id="btn_export" data-token="{{ csrf_token() }}"><i class="bi bi-file-earmark-arrow-down-fill fs-4"></i></a>
                    <a href="javascript:;" class="btn btn-icon btn-danger me-2" id="btn_delete" data-token="{{ csrf_token() }}"><i class="bi bi-trash-fill fs-4"></i></a>
                </div>
                <!--end::Toolbar-->
            </div>
            <!--end::Card toolbar-->
        </div>
        <!--end::Card header-->
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
                            <th class="text-start">{{__('lang.title')}}</th>
                            <th class="text-start">{{__('lang.student')}}</th>
                            <th class="text-start min-w-100px">{{__('lang.actions')}}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 fw-semibold">
                    </tbody>
                </table>
            </div>
            <!--end::Table-->
        </div>
        <!--end::Card body-->
    </div>
    <!--end::Card-->
</div>


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
            "loadingRecords": "{{__('lang.loadingRecords')}}"
        },
        ajax: {
            url: "{{ route($route.'.index') }}",
            data: function (d) {
                d.from_date = $('#kt_datepicker_1').val(),
                d.to_date = $('#kt_datepicker_2').val(),
                d.search = $('#search').val()
            }
        },
        lengthMenu: [
            [10, 25, 50, 100],
            ['10', '25', '50', '100']
        ],
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'checkbox', name: 'checkbox'},
            {data: 'title', name: 'title'},
            {data: 'student', name: 'student'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    const filterSearch = document.querySelector('[data-kt-db-table-filter="search"]');
    filterSearch.addEventListener('keyup', function (e) {
        table.draw();
    });
    
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });
    
    $('#filter-form').on('reset', function(e) {
        e.preventDefault();
        $(this).find('input, select').val('');
        table.ajax.reload();
    });

    $("#btn_export").click(function (event) {
        event.preventDefault();


        var params = {
            from_date : $('#kt_datepicker_1').val(),
            to_date : $('#kt_datepicker_2').val(),
            search : $('#search').val()
        };

        window.location = "{{ route($route.'.export') }}?" + $.param(params);

    });

    $("#btn_delete").click(function (event) {
        event.preventDefault();
        var checkIDs = $("#kt_table_list input:checkbox:checked").map(function () {
            return $(this).val();
        }).get(); // <----

        if (checkIDs.length > 0) {
            var token = $(this).data("token");

            const button = document.getElementById('kt_docs_sweetalert_html');

            Swal.fire({
                title: '{{__("lang.confirm_delete")}}',
                text: "{{__('lang.cannot_restore')}}",
                icon: "info",
                buttonsStyling: false,
                showCancelButton: true,
                confirmButtonText: "{{__('lang.confirm')}}",
                cancelButtonText: "{{__('lang.cancel')}}",
                customClass: {
                    confirmButton: "btn btn-primary",
                    cancelButton: 'btn btn-danger'
                }
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
                                if (data.message == "success") {
                                    table.draw();
                                    toastr.success("", "{{__('lang.deleted_successfully')}}");
                                } else {
                                    toastr.error("", "{{__('lang.delete_failed')}}");
                                }
                            },
                            fail: function (xhrerrorThrown) {
                                toastr.error("", "{{__('lang.delete_failed')}}");
                            }
                        });
                } else {
                    toastr.error("", "{{__('lang.delete_cancelled')}}");
                }
            });

        } else {
            toastr.error("", "{{__('lang.select_items_first')}}");
        }
    });

});


</script>
@endsection