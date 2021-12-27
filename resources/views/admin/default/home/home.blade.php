@extends('admin.default.layouts.baseCont')
@section('content')
    <div class="layui-row layui-col-space15">
        <div class="layui-card shadow panel">

            <div class="layui-card-header">{{ lang('版本信息') }}
                <div class="panel-action"  >
                    <a href="#" data-perform="panel-collapse"><i  title="点击可折叠" class="layui-icon layui-icon-subtraction"></i></a>
                </div>
            </div>
            <div class="layui-card-body ">
                <div class="table-responsive">

                    <table class="layui-table layui-text">

                        <tbody>
                        <tr>
                            <td>
                                {{ lang("系统名称") }}
                            </td>
                            <td>
                                Saol后台管理
                            </td>
                        </tr>
                        <tr>
                            <td>{{ lang("当前版本") }}</td>
                            <td>
                               2.0
                            </td>
                        </tr>
                        <tr>
                            <td>更新时间</td>
                            <td>2022/01/01</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection
