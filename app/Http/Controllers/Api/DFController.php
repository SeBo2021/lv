<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\PayLog;
use App\Services\Pay;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PayTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\InvalidArgumentException;
use App\TraitClass\IpTrait;

/**
 * 艾希支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class DFController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    /**
     * 支付动作
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function pay(Request $request): JsonResponse
    {

        // TODO: Implement pay() method.
        $params = ApiParamsTrait::parse($request->params ?? '');
        Validator::make($params, [
            'pay_id' => 'required|string',
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2']),
            ],
        ])->validate();
        //Log::info('df_pay_params===', [$params]);//参数日志
        $payEnv = self::getPayEnv();
        $secret = $payEnv['DF']['secret'];

        $payInfo = PayLog::query()->find($params['pay_id']);
        if (!$payInfo) {
            throw new Exception("记录不存在");
        }

        $orderInfo = Order::query()->find($payInfo['order_id']);
        if (!$orderInfo) {
            throw new Exception("订单不存在");
        }

        $channelNo = $params['type'];
        if (in_array($params['type'], ['1', '2'])) {
            $channelNo = $this->getOwnMethod($orderInfo->type, $orderInfo->type_id, $params['type']);
        }

        $mercId = $payEnv['DF']['merchant_id'];
        // $notifyUrl = env('APP_URL') . $payEnv['DF']['notify_url'];
        // $notifyUrl = 'https://qa.saoltv.com' . $payEnv['DF']['notify_url'];
        $notifyUrl = 'http://api.saolv200.com' . $payEnv['DF']['notify_url'];
        $input = [
            'p1_merchantno' => $mercId,               //商户号
            'p2_amount' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
            'p3_orderno' => strval($payInfo->number),           //订单号，值允许英文数字
            'p4_paytype' => $channelNo,            //支付通道编码
            'p5_reqtime' => date('YmdHis'),//支付发起时间
            'p6_goodsname' => $orderInfo->id,              //商品名称
            //'p7_bankcode' => '', //【可选】银行编码: 付款银行的编码，仅在网关支付产品中有意义, 其他支付产品请传递空白字符串或忽略该参数。
            'p8_returnurl' => 'https://dl.yinlian66.com',     //同步跳转 URL
            'p9_callbackurl' => $notifyUrl,              //后台异步通知 (回调) 地址
        ];
        //生成签名 请求参数按照Ascii编码排序
        //MD5 签名: HEX 大写, 32 字节。
        $input['sign'] = $this->sign($input, $secret);
        Log::info('df_third_params===', [$input]);//三方参数日志
        $curl = (new Client([
            //  'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false,
        ]))->post($payEnv['DF']['pay_url'], ['form_params' => $input]);

        $response = $curl->getBody();
        //Log::info('df_third_response===', [$response]);//三方响应日志
        $resJson = json_decode($response, true);
        if ($resJson['rspcode'] == 'A0') {
            $return = $this->format(0, ['data' => $resJson['data']??''], '取出成功');
        } else {
            $return = $this->format(-1, new \StdClass(), $response);
        }
        // 强制转换
        /*try {

        } catch (Exception | InvalidArgumentException $e) {
            $return = $this->format($e->getCode(), new \StdClass(), $e->getMessage());
        }*/
        return response()->json($return);
    }

    /**
     * 订单回调
     * @param Request $request
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function callback(Request $request): mixed
    {
        // TODO: Implement callback() method.
        $postResp = $request->post();
        Log::info('df_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['DF']['secret'];
            $signPass = $this->verify($postResp, $secret, $postResp['sign']);
            if (!$signPass) {
                // 签名验证不通过
                throw new Exception('签名验证不通过', -1);
            }else{
                // 记录支付信息
                DB::beginTransaction();
                $this->orderUpdate($postResp['orderid'], $postResp);
                DB::commit();
            }

            $return = 'SUCCESS';
        } catch (Exception $e) {
            Log::info('df_error_callback===', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);//三方返回参数日志
            DB::rollBack();
            $return = 'FAILED';
        }
        return response($return);
    }

    public function method(Request $request): mixed
    {
        // TODO: Implement method() method.
        return '';
    }

    function sign($data, $md5Key): string
    {
        /*$native = array(
            "pay_memberid" => $data['pay_memberid'],
            "pay_orderid" => $data['pay_orderid'],
            "pay_amount" => $data['pay_amount'],
            "pay_applydate" => $data['pay_applydate'],
            "pay_bankcode" => $data['pay_bankcode'],
            "pay_notifyurl" => $data['pay_notifyurl'],
            "pay_callbackurl" => $data['pay_callbackurl'],
        );*/
        $native = $data;
        ksort($data);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5Key));
        return $sign;
    }

    /**
     * 验签
     * @param $data
     * @param $md5Key
     * @param $pubKey
     * @return bool
     */
    function verify($data, $md5Key, $pubKey): bool
    {
        $returnArray = array( // 返回字段
            "p1_merchantno" => $data["p1_merchantno"],
            'p2_amount' => $data["p2_amount"],
            'p3_orderno' => $data["p3_orderno"],
            'p4_paytype' => $data["p4_status"],
            'p5_producttype' => $data["p5_producttype"],
            'p6_requesttime' => $data["p6_requesttime"],
            'p7_goodsname' => $data["p7_goodsname"],
            'p8_tradetime' => $data["p8_tradetime"],
            'p9_porderno' => $data["p9_porderno"],
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5Key));
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}