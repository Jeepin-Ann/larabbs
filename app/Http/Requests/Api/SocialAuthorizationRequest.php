<?php

namespace App\Http\Requests\Api;

use Dingo\Api\Http\FormRequest;

class SocialAuthorizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        //验证请求过来的值，符合规则。code和token
        return [
            'code' => 'required_without:access_token|string',
            'access_token' => 'required_without:code|string',
        ];
        //如果请求过来weixin，且没有code，就把请求的openid返回，为了和token配合。
        if ($this->social_type == 'weixin' && !$this->code){
            $rules['oppenid'] = 'required|string';
        }

        return $rules;
    }
}
