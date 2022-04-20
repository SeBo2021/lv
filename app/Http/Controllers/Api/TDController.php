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
 * 通达支付
 * Class AXController
 * @package App\Http\Controllers\Api
 */
class TDController extends PayBaseController implements Pay
{
    use PayTrait;
    use ApiParamsTrait;
    use IpTrait;

    public string $payFlag = 'td';

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
        $payEnvInfo = $payEnv['TD'];
        $secret = $payEnvInfo['secret'];

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

        $mercId = $payEnvInfo['merchant_id'];
        // $notifyUrl = env('APP_URL') . $payInfo['notify_url'];
        $notifyUrl = 'https://qa.saoltv.com' . $payInfo['notify_url'];
//        $notifyUrl = 'http://api.saolv200.com' . $payEnvInfo['notify_url'];
        $input = [
            'mch_id' => $mercId,               //商户号
            'pass_code' => $channelNo,            //通道类型
            'subject' => $orderInfo->id,              //订单标题
            'out_trade_no' => strval($payInfo->number),           //订单号，值允许英文数字
            'amount' => intval($orderInfo->amount ?? 0),              //订单金额,单位元保留两位小数
            'client_ip' => $this->getRealIp(),            //客户IP地址
            'notify_url' => $notifyUrl,              //后台异步通知 (回调) 地址
            'timestamp' => date('Y-m-d H:i:s'),//支付发起时间
        ];
        //生成签名 请求参数按照Ascii编码排序
        //MD5 签名: HEX 大写, 32 字节。
        $input['sign'] = $this->sign($input, $secret);
        Log::info($this->payFlag.'_third_params===', [$input]);//三方参数日志
        $curl = (new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false,
        ]))->post($payEnvInfo['pay_url'], ['form_params' => $input]);

        $response = $curl->getBody();
        Log::info($this->payFlag.'_third_response===', [$response]);//三方响应日志
        $resJson = json_decode($response, true);
        if ($resJson['code'] == 0) {
            $return = $this->format(0, ['data' => $resJson['data']??''], '取出成功');
        } else {
            $return = $this->format($resJson['code'], $resJson, $resJson['msg']??'');
        }
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
        Log::info($this->payFlag.'_pay_callback===', [$postResp]);//三方返回参数日志
        try {
            $payEnv = self::getPayEnv();
            $secret = $payEnv['TD']['secret'];
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
        $native = $data;
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($md5str . "key=" . $md5Key));
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
        $sign = $this->sign($data,$md5Key);
        if ($sign == $pubKey) {
            return true;
        }
        return false;
    }
}