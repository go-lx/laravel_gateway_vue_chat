<?php

namespace App\Http\Controllers;

use GatewayClient\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Message;
use App\User;

class HomeController extends Controller
{
    public function __construct()
    {
        // auto验证
        $this->middleware('auth');
        // 设置GatewayWorker服务的Register服务ip和端口
        Gateway::$registerAddress = '127.0.0.1:1238';
    }


    /**
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request){
        //获取房间号
        $room_id = $request->room_id ?? '1';
        session()->put('room_id', $room_id);

        return view("home");
    }

    /**
     * 初始化
     * @param Request $request
     */
    public function init(Request $request)
    {
        //绑定用户
        $this->bind($request);
        //进入聊天室了
        $this->login();
        //在线用户
        $this->users();
        // 历史记录
        $this->history();


    }


    /**
     * 提示进入聊天室
     */
    private function login()
    {
        // 返回数据给客户端
        $data = [
            'type' => 'say',
            'data' => [
                'avatar' => Auth::user()->avatar(),// 用户头像
                'name' => Auth::user()->name, // 用户昵称
                'content' => '进入了聊天室', // 内容
                'time' => date("Y-m-d H:i:s", time()) // 时间
            ]
        ];
        // 发给指定群聊
        Gateway::sendToGroup(session('room_id'), json_encode($data));
    }



    /**
     * 绑定client_id 与 user id
     * @param Request $request
     */
    private function bind(Request $request)
    {
        // 获取用户id
        $id = Auth::id();
        // 获取客户端id
        $client_id = $request->get('client_id');
        // 绑定用户id 和 客户端id
        Gateway::bindUid($client_id, $id);
        // 绑定组id
        Gateway::joinGroup($client_id, session('room_id'));

        // 设置Gateway session消息 实现在线用户列表
        Gateway::setSession($client_id, [
            'id' => $id,
            'avatar' => Auth::user()->avatar(),
            'name' => Auth::user()->name
        ]);

    }


    /**
     * 发送消息
     * @param Request $request
     * @throws \Exception
     */
    public function sendMsg(Request $request)
    {
        $data = [
            'type' => 'say',
            'data' => [
                'avatar' => Auth::user()->avatar(),
                'name' => Auth::user()->name,
                'content' => $request->input('content'),
                'time' => date("Y-m-d H:i:s", time())
            ]
        ];

        //私聊 如果传递了send_to_id
        if ($request->user_id) {
            $data['data']['name'] = Auth::user()->name . ' 对 ' . User::find($request->user_id)->name . ' 说：';
            Gateway::sendToUid($request->user_id, json_encode($data)); // 给对方发送一条消息
            Gateway::sendToUid(Auth::id(), json_encode($data));// 给当前用户发送一条消息

            //私聊信息，只发给对应用户，不存数据库了
            return;
        }

        // 发给指定群聊
        Gateway::sendToGroup(session('room_id'), json_encode($data));


        //存入数据库，以后可以查询聊天记录
        Message::create([
            'user_id' => Auth::id(),
            'room_id' => session('room_id'),// 群聊id
            'content' => $request->input('content')
        ]);
    }

    /***
     * 最新的20条聊天历史信息
     */
    private function history()
    {
        $data = ['type' => 'history'];
        // 查询该用户相关房间号的历史聊天记录
        $messages = Message::with('user')->where('room_id', session('room_id'))->orderBy('id', 'desc')->limit(20)->get();
        $data['data'] = $messages->map(function ($item, $key) { // 遍历 处理数据格式
            return [
                'avatar' => $item->user->avatar(),
                'name' => $item->user->name,
                'content' => $item->content,
                'time' => $item->created_at->format("Y-m-d H:i:s")
            ];
        });
        // 返回给指定用户
        Gateway::sendToUid(Auth::id(), json_encode($data));
    }

    /**
     * 当前在线用户
     */
    private function users()
    {
        $data = [
            'type' => 'users',
            'data' => Gateway::getClientSessionsByGroup(session('room_id')),
        ];
        // 发给指定群聊
        Gateway::sendToGroup(session('room_id'), json_encode($data));
    }

}
