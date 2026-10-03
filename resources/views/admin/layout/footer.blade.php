<!--begin::Footer-->
<div class="footer py-4 d-flex flex-column flex-md-row flex-stack" id="kt_footer">
    <!--begin::Copyright-->
    <div class="text-gray-900 order-2 order-md-1">
        <span class="text-muted fw-semibold me-1">{{date('Y')}} &copy;</span>
        <a href="https://arabiacode.com" target="_blank" class="text-gray-800 text-hover-primary">Arabiacode.com</a>
    </div>
    <!--end::Copyright-->
    <!--begin::Menu-->
    <ul class="menu menu-gray-600 menu-hover-primary fw-semibold order-1">
        <li class="menu-item">
            <a href="javascript:;" target="_blank" class="menu-link px-2">{{(App::getLocale() == 'ar') ? setting('name_ar') : setting('name_en')}}</a>
        </li>
    </ul>
    <!--end::Menu-->
</div>
<!--end::Footer-->