<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Api\AuthorizationRequest;
use App\Http\Requests\Api\SocialAuthorizationRequest;

class AuthorizationsController extends Controller
{
    //第三方登录
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
        $token = Auth::guard('api')->fromUser($user);
        return $this->respondWithToken($token)->setStatusCode(201);
    }

    //登录
    public function store(AuthorizationRequest $request)
    {
        $username = $request->username;

        filter_var($username, FILTER_VALIDATE_EMAIL) ?
            $credentials['email'] = $username :
            $credentials['phone'] = $username;
        $credentials['password'] = $request->password;

        if(!$token = Auth::guard('api')->attempt($credentials)){
            return $this->response->errorUnauthorized('用户名或密码错误');
        }
        return $this->respondWithToken($token)->setStatusCode(201);

    }

    protected function respondWithToken($token)
    {
        return $this->response->array([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }
    //刷新token
    public function update()
    {
        $token = Auth::guard('api')->refresh();
        return $this->respondWithToken($token);
    }
    //删除token
    public function destroy()
    {
        $token = Auth::guard('api')->logout();
        return $this->response()->noContent();
    }
}
