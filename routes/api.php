<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$useSecret = \Illuminate\Support\Env::get("MIDDLEWARE_SECRET");

Route::group([
    'namespace'=>'Api',
    'middleware' => $useSecret ? ['secret'] : []
],function (){
    Route::get('config', 'ConfigController@ack');   //配置
    Route::post('login', 'AuthController@login');
});

Route::group([
    'namespace'=>'Api',
],function (){
    //Route::post('kfUrl','ForwardController@kfAgent'); //客服转发服务链接
    if(\Illuminate\Support\Env::get("APP_ENV")!=='production'){
        Route::post('aes_en', 'TestController@aes_en');
        Route::post('aes_de', 'TestController@aes_de');
    }
    Route::post('callbackDbs', 'DBSController@callback');  //大白鲨支付回调
    Route::post('callbackCJ', 'CJController@callback');  //长江支付回调
    Route::post('callbackYK', 'YKController@callback');  //YK支付回调
    Route::post('callbackYKGame', 'YKGameController@callback');  //YKGame支付回调
    Route::post('callbackXD', 'XDController@callback');  //信达支付回调
    Route::post('callbackAX', 'AXController@callback');  //艾希支付回调
    Route::get('callbackDF', 'DFController@callback');  //大发支付回调
    Route::get('callbackXF', 'XFController@callback');  //兴付支付回调
    Route::post('callbackTD', 'TDController@callback');  //通达支付回调
    Route::post('callbackSA', 'SAController@callback');  //SA支付回调(易付)
});

Route::group([
    'namespace'=>'Api',
    'middleware' => $useSecret ? ['auth:api','secret','scopes:check-user'] : ['auth:api','scopes:check-user']
], function() {
    Route::get('getAreaNum','UserController@getAreaNum'); //获取国际码
    Route::get('rechargeMethods','RechargeController@methods'); //充值方式
    Route::post('recharge','RechargeController@submit'); //充值
    Route::post('viewShare','VideoController@actionShare'); //分享
    Route::post('viewCollect','VideoController@actionCollect'); //收藏
    Route::post('viewLike','VideoController@actionLike'); //点赞/喜欢
    Route::post('viewVideo','VideoController@actionView'); //观看
    Route::get('hotWords', 'SearchController@hotWords');    //热搜关键词
    Route::get('search', 'SearchController@index');    //搜索
    Route::get('searchOption', 'SearchController@getOption');    //搜索
    Route::get('searchTag', 'SearchController@tag');    //标签内容接口
    Route::get('hotTags', 'SearchController@hotTags');    //热门标签
    Route::get('searchRecommend', 'SearchController@recommend'); //推荐
    Route::get('searchCat', 'SearchController@cat');    //分类搜索-更多
    Route::post('lists', 'HomeController@lists');      //列表
    Route::get('rechargeActivity', 'HomeController@rechargeActivity'); //充值活动
    Route::get('category', 'HomeController@category'); //分类
    Route::get('carousel', 'HomeController@carousel'); //轮播
    Route::post('commentList', 'CommentController@lists');      //评论列表
    Route::post('comment', 'CommentController@submit');      //评论
    Route::post('commentReply', 'CommentController@reply');  //回复评论
    Route::get('logout', 'AuthController@logout');   //登出接口
    Route::get('vipInfo', 'VipController@memberCards');     //会员卡
    Route::get('gold', 'VipController@gold');     //骚豆
    Route::get('userExtendInfo', 'UserController@extendInfo');     //用户扩展信息
    Route::post('user', 'UserController@set');     //用户设置
    Route::get('myShare', 'UserController@myShare');     //我的分享
    Route::post('billing ', 'UserController@billing');     //账单记录
    Route::post('billingClear ', 'UserController@billingClear');     //账单记录
    Route::get('myCollect', 'UserController@myCollect');     //收藏列表
    Route::get('viewHistory', 'UserController@viewHistory');     //观看历史
    Route::post('bindInviteCode','UserController@bindInviteCode'); //手动绑定邀请码
    Route::post('bindPhone','UserController@bindPhone'); //绑定手机
    Route::post('sendSmsCode','UserController@sendSmsCode'); //发送短信验证码
    Route::post('findADByPhone','UserController@findADByPhone'); //手机找回账号
    //Route::post('uploadVideo', 'FileUploadController@uploadVideo'); //上传视频
    //Route::post('uploadImg', 'FileUploadController@uploadImg'); //上传图片
    //Route::post('userVideo', 'UserController@videoList'); //用户上传的视频列表
    //Route::post('upgrade', 'ConfigController@upgrade');  //升级
    /// 大白鲨相关接口
    Route::post('payDbs', 'DBSController@pay');  //大白鲨支付动作
    Route::post('queryDbs', 'DBSController@query');  //大白鲨支付查询
    Route::post('methodDbs', 'DBSController@method');  //大白鲨支付方式

    /// 长江支付相关接口
    Route::post('payCJ', 'CJController@pay');  //长江支付动作
    /// YK支付相关接口
    Route::post('payYK', 'YKController@pay');  //YK支付动作
    //YKGame支付
    Route::post('payYKGame', 'YKGameController@pay');  //YK支付动作
    /// YK支付相关接口
    Route::post('payXD', 'XDController@pay');  //信达支付动作
    /// 艾希支付相关接口
    Route::post('payAX', 'AXController@pay');  //艾希支付动作
    // 锦绣支付
    Route::post('payJX', 'JXController@pay');  //锦绣支付动作
    // 大发支付
    Route::post('payDF', 'DFController@pay');  //大发支付动作
    // 兴付
    Route::post('payXF', 'XFController@pay');  //兴付支付动作
    // 通达支付
    Route::post('payTD', 'TDController@pay');  //通达支付动作
    // SA支付
    Route::post('paySA', 'SAController@pay');  //SA支付动作
    /// 订单相关接口
    Route::post('oderCreate', 'OrderController@create');  //订单创建接口
    Route::get('orderQuery', 'OrderController@query');  //订单查询接口
    Route::post('orderPay', 'OrderController@orderPay');  //订单支付接口

    /// 社区模块
    Route::get('commCate', 'CommCateController@info');  //板块分类
    Route::get('commCity', 'CommMiscController@city');  //地区列表
    Route::get('commList', 'CommContentController@lists');  //内容列表
    Route::get('commDetail', 'CommContentController@detail');  //内容列表
    Route::post('commFocus', 'CommOperationController@foucs');  //关注用户
    Route::post('commLike', 'CommOperationController@like');  //点赞用户
    Route::get('commComment', 'CommCommentController@lists');  //帖子评论
    Route::post('commCommentPost', 'CommCommentController@post');  //发表评论
    Route::post('commReward', 'CommRewardController@action');  //打赏
    Route::get('commMessage', 'CommMessageController@lists');  //消息列表
    Route::get('commHome', 'CommHomeController@info');  //个人详情
    Route::get('commChat', 'CommChatController@lists');  //私聊-消息列表
    Route::post('commChatPost', 'CommChatController@post');  //私聊-发送消息
    Route::post('commRes', 'CommMiscController@res');  //发送资源
    Route::post('commBbs', 'CommContentController@post');  //发贴

    /// 小视频模块
    Route::get('shortCate', 'VideoShortController@cate');  //视频分类
    Route::get('shortList', 'VideoShortController@lists');  //播放列表
    Route::post('shortLike', 'VideoShortController@like');  //小视频点赞
    Route::post('shortCollect', 'VideoShortController@collect');  //收藏
    /// 小视频评论
    Route::post('shortCommentList', 'CommentShortController@lists');      //评论列表
    Route::post('shortComment', 'CommentShortController@submit');      //评论
    Route::post('shortCommentReply', 'CommentShortController@reply');  //回复评论

    /// 伪直播模块
    Route::get('liveList', 'FakeLiveShortController@lists');  //播放列表
    Route::post('liveCalc', 'FakeLiveShortController@calc');  //直播统计
});
