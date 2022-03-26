@extends('admin.default.layouts.baseCont')
@section('content')
    <style>
            
        </style>
        <div class="layui-card shadow panel ">
            <div class="layui-card-header ">
                控制台
                <div class="panel-action">
                    <a href="#" data-perform="panel-collapse"><i title="点击可折叠" class="layui-icon layui-icon-subtraction"></i></a>
                </div>
            </div>

            <div class="layui-card-body" id="collapseSearch">

                <div class="layui-form layui-form-pane">
                <form class="layui-form" method="POST" action="/admin/redisOperate/submit">
                {{ csrf_field() }}
                    <div class="layui-form-item">
                        <label class="layui-form-label">分片</label>
                            <div class="layui-input-block">
                                <input type="radio" name="type" value="0" title="0" @if($type==0) checked="" @endif >
                                <input type="radio" name="type" value="1" title="1" @if($type==1) checked="" @endif >
                                <input type="radio" name="type" value="2" title="2" @if($type==2) checked="" @endif >
                                <input type="radio" name="type" value="3" title="3" @if($type==3) checked="" @endif >
                            </div>
                        </div>

                    <div class="layui-form-item">
                        <div class="layui-inline">
                        <label class="layui-form-label">命令</label>
                        <div class="layui-input-inline" style="width: 150px;">
                            <input type="text" name="method" value="{{ $method??'' }}" placeholder="set/get/lrange/sadd" autocomplete="off" class="layui-input">
                        </div>
                        <div class="layui-form-mid">-</div>
                        <div class="layui-input-inline" style="width: 300;">
                            <input type="text" name="parameters" value="{{ $parameters??'' }}" placeholder="" autocomplete="off" class="layui-input">
                        </div>
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <div class="layui-input-block " style="margin-left: 0;">
                            <button type="submit" class="layui-btn" lay-submit="" lay-filter="activeSubmit">提交</button>
                            <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                        </div>
                    </div>
                </form>
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">结果</label>
                        <div class="layui-input-block">
                            <textarea placeholder="显示内容" class="layui-textarea" rows="25" readonly>{{ $res??'' }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        

        <script src="{{ ___('admin/layui/layui.js',$res_version??'') }}"></script>
        <script src="{{ ___('admin/jquery/jquery.min.js',$res_version??'') }}"></script>
        <script>
            
        </script>

    @endsection




