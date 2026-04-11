<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\TuyenDuong\StoreTuyenDuongRequest;
use App\Http\Requests\TuyenDuong\UpdateTuyenDuongRequest;
use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherStatusRequest;
use App\Http\Requests\Xe\StoreXeRequest;
use App\Http\Requests\Xe\UpdateXeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

trait ResolvesForwardedFormRequests
{
    protected function resolveFormRequest(string $class, Request $request): FormRequest
    {
        /** @var FormRequest $form */
        $form = $class::createFrom($request);
        $form->setContainer(app());
        if (app()->bound('redirect')) {
            $form->setRedirector(app('redirect'));
        }
        $form->validateResolved();

        return $form;
    }

    protected function storeXeRequest(Request $request): StoreXeRequest
    {
        /** @var StoreXeRequest $form */
        $form = $this->resolveFormRequest(StoreXeRequest::class, $request);

        return $form;
    }

    protected function updateXeRequest(Request $request): UpdateXeRequest
    {
        /** @var UpdateXeRequest $form */
        $form = $this->resolveFormRequest(UpdateXeRequest::class, $request);

        return $form;
    }

    protected function storeTuyenDuongRequest(Request $request): StoreTuyenDuongRequest
    {
        /** @var StoreTuyenDuongRequest $form */
        $form = $this->resolveFormRequest(StoreTuyenDuongRequest::class, $request);

        return $form;
    }

    protected function updateTuyenDuongRequest(Request $request): UpdateTuyenDuongRequest
    {
        /** @var UpdateTuyenDuongRequest $form */
        $form = $this->resolveFormRequest(UpdateTuyenDuongRequest::class, $request);

        return $form;
    }

    protected function storeVoucherRequest(Request $request): StoreVoucherRequest
    {
        /** @var StoreVoucherRequest $form */
        $form = $this->resolveFormRequest(StoreVoucherRequest::class, $request);

        return $form;
    }

    protected function updateVoucherStatusRequest(Request $request): UpdateVoucherStatusRequest
    {
        /** @var UpdateVoucherStatusRequest $form */
        $form = $this->resolveFormRequest(UpdateVoucherStatusRequest::class, $request);

        return $form;
    }
}
