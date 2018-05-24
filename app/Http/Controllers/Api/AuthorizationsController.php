<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\Api\SocialAuthorizationRequest;

class AuthorizationsController extends Controller
{
    public function socialStore(SocialAuthorizationRequest $request, $type){
        //如果在type中没有‘weixin’，‘weibo’，‘QQ’这样的类型。没有返回错误请求的响应，就是说不支持weixin
        if (!in_array($type,['weixin'])){
            return $this->response->errorBadRequest();
        }
        //如果是‘weixin’，通过ytpe得到driver信息
        $driver = \Socialite::driver($type);
        try{
            //如果是请求回来code
            if($code = $request->code){
                //通过code获取token响应
                $response = $driver->getAccessTokenResponse($code);
                //从响应中取出token
                $token = array_get($response, 'access_token');
            }else{//如果请求回来的不是code，
                //从请求中获取token
                $token = $request->access_token;
                //如果类型值等于weinxin，设置openid
                if($type == 'weixin'){
                    //给driver设置设置openid。
                    $driver->setOpenID($request->openid);
                }
            }
            //通过token获取用户信息
            $oauthUser = $driver->userFromToken($token);
        } catch(\Exception $e){
            return $this->response->errorUnauthorized('参数错误，未获取到用户信息');
        }
        //情况一：如果是weixin
        switch($type){
            case 'weixin':
            //如果获取回来的$oauthUser中存在unionid就获取并设置，没有就设置null
            $unionid = $oauthUser->offsetExists('unionid')? $oauthUser->offsetGet('unionid') : null;
            //如果不空，就用weixin_unionid（值就是$unionid）从数据库查找该用户
            if($unionid){
                $user = User::where('weixin_unionid', $unionid)->first();
            }else{
                //如果为空，就通过weixin_openid（用户信息中的ID）获取用户信息。
                $user = User::where('weixin_openid', $oauthUser->getID())->first();
            }
            
            //没有以上两个都没有找到用户，默认创建用户
            if(!$user){
                $user = User::create([
                    'name' =>$oauthUser->getNickname(),
                    'avatar' =>$oauthUser->getAvatar(),
                    'weixin_openid' => $oauthUser->getID(),
                    'weixin_unionid' => $unionid,
                ]);
            }
            break;
        }
        return $this->response->array(['token' => $user->id]);
    }
}
