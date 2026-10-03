<div class="row">
    <div class="col-lg-12">

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="required">{{ __('lang.symbol') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="symbol" value="{{old('symbol',$data->symbol ?? '')}}" placeholder="{{ __('lang.symbol') }}" />
            </div>
        </div>

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
                    <span class="">{{ __('lang.arabic_name') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="arabic_name" value="{{old('arabic_name',$data->arabic_name ?? '')}}" placeholder="{{ __('lang.arabic_name') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.isin') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="isin" value="{{old('isin',$data->isin ?? '')}}" placeholder="{{ __('lang.isin') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.market') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="market" value="{{old('market',$data->market ?? 'egypt')}}" placeholder="{{ __('lang.market') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.exchange') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="exchange" value="{{old('exchange',$data->exchange ?? '')}}" placeholder="{{ __('lang.exchange') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.asset_class') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="asset_class" value="{{old('asset_class',$data->asset_class ?? 'STOCK')}}" placeholder="{{ __('lang.asset_class') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.industry') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="industry" value="{{old('industry',$data->industry ?? '')}}" placeholder="{{ __('lang.industry') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.reuters_symbol') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-solid" name="reuters_symbol" value="{{old('reuters_symbol',$data->reuters_symbol ?? '')}}" placeholder="{{ __('lang.reuters_symbol') }}" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.logo') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                @isset($data)
                    @if($data->logo)
                        <div class="mb-3">
                            <img src="{{ asset('storage/'.$data->logo) }}" alt="{{ $data->name }}" style="max-height:60px" />
                        </div>
                    @endif
                @endisset
                <input type="file" class="form-control form-control-solid" name="logo" />
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">
                    <span class="">{{ __('lang.description') }}</span>
                </label>
            </div>
            <div class="col-md-9">
                <textarea name="description" class="form-control form-control-solid" rows="4" placeholder="{{ __('lang.description') }}">{{old('description',$data->description ?? '')}}</textarea>
            </div>
        </div>

        <div class="row fv-row mb-7">
            <div class="col-md-3 text-md-end">
                <label class="fs-6 fw-semibold form-label mt-3">{{ __('lang.status') }}</label>
            </div>
            <div class="col-md-9">
                @php
                    $flags = [
                        'is_tradable' => 'is_tradable',
                        'is_visible' => 'is_visible',
                        'is_otc' => 'is_otc',
                        'is_right' => 'is_right',
                        'is_ipo' => 'is_ipo',
                        'is_same_day' => 'is_same_day',
                        'is_sharia_compliant' => 'is_sharia_compliant',
                        'is_egx30' => 'is_egx30',
                        'is_egx70' => 'is_egx70',
                        'is_egx100' => 'is_egx100',
                    ];
                @endphp
                @foreach($flags as $field => $label)
                    <div class="form-check form-check-custom form-check-solid mb-3">
                        <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}"
                            {{ old($field, $data->{$field} ?? ($field == 'is_tradable' || $field == 'is_visible')) ? 'checked' : '' }} />
                        <label class="form-check-label" for="{{ $field }}">{{ __('lang.'.$label) }}</label>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
