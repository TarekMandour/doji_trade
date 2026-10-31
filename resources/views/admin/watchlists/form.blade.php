<div class="row">
    <div class="col-lg-12">

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.name') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="name" value="{{old('name',$data->name ?? '')}}" placeholder="{{ __('lang.name') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.stocks') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                @php
                    $selectedIds = old('stock_ids', isset($data) ? $data->stocks->pluck('id')->toArray() : []);
                @endphp
                <select class="form-select" data-control="select2" multiple="multiple" name="stock_ids[]" data-placeholder="{{ __('lang.choose') }}" id="kt_stocks_select">
                    @foreach($stocks as $stock)
                        <option value="{{ $stock->id }}" {{ in_array($stock->id, $selectedIds) ? 'selected' : '' }}>{{ $stock->symbol }} - {{ $stock->arabic_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>
</div>

<script>
    $('#kt_stocks_select').select2({
        placeholder: "{{ __('lang.choose') }}",
    });
</script>
