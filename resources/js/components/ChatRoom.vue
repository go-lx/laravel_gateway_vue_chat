<template>
    <div class="container">
        <a href="?room_id=1" class="btn btn-danger">吃货人生</a>
        <a href="?room_id=2" class="btn btn-primary">技术探讨</a>
        <hr class="divider">

        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">聊天室</div>
                    <div class="panel-body">

                        <!-- 消息开始 -->
                        <div class="messages">
                            <div class="media" v-for="message in messages">
                                <div class="media-left" style="margin-right: 10px;">
                                    <a href="#">
                                        <img class="media-object img-circle" :src="message.avatar">
                                    </a>
                                </div>

                                <div class="media-body">
                                    <p class="time">{{message.time}}</p>
                                    <h4 class="media-heading">{{message.name}}</h4>
                                    {{message.content}}
                                </div>
                            </div>
                        </div>
                        <!-- 消息结束 -->

                    </div>
                </div>
            </div>

            <div class="col-md-4">

                <div class="panel panel-default">
                    <div class="panel-heading">在线用户</div>

                    <div class="panel-body">
                        <ul class="list-group">
                            <li class="list-group-item" v-for="user in users">
                                <img :src="user.avatar" class="img-circle">
                                {{user.name}}
                            </li>

                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div style="height:10px;"></div>

        <form @submit.prevent="onSubmit">
            <div class="form-group">
                <label for="user_id">私聊</label>
                <select class="form-control" id="user_id" v-model="user_id">
                    <option>所有人</option>
                    <option :value="user.id" v-for="user in users">{{user.name}}</option>
                </select>
            </div>

            <div class="form-group">
                <label for="content">内容</label>
                <textarea class="form-control" rows="3" id="content" v-model="content"></textarea>
            </div>

            <button type="submit" class="btn btn-success">提交</button>
        </form>
    </div>
</template>

<script>
    // ws 服务器地址
    let ws = new WebSocket("ws://0.0.0.0:7788");

    export default {
        data() {
            return {
                messages: [],//消息结合
                content: '',//消息内容
                users: [],// 在线用户集合
                send_to_id: '' // 发送给指定用户
            }
        },
        created() {
            ws.onmessage = (e) => {
                //字符串转json
                let data = JSON.parse(e.data);
                //如果没有类型，就为空
                let type = data.type || '';

                switch (type) {
                    case "init":
                        // 初始化
                        axios.post('/init', {client_id: data.client_id});
                        break;
                    case 'say':
                        // 接收消息
                        this.messages.push(data.data);
                        // 等dom更新后 设置滚动条
                        this.$nextTick(function () {
                            $('.panel-body').animate({scrollTop: $('.messages').height()});
                        });
                        break;
                    case 'history':
                        // 历史记录
                        this.messages = data.data;
                        break;
                    case 'users':
                        // 在线用户
                        this.users = data.data;
                        break;
                    case 'logout':
                        // 退出登录
                        this.$delete(this.users, data.client_id);
                        break;
                    case 'ping':
                        // 心跳回应
                        ws.send('pong');
                        break;

                    default:
                        console.log(data)
                }

                //如果没有类型，就为空
                // let type = data.type || '';
            }
        },
        methods: {
            // 发送信息
            onSubmit() {
                if (this.content.length <= 0) return;
                axios.post('/send-msg', {content: this.content, user_id: this.user_id});
                this.content = ''
            }
        }
    }
</script>


<style scoped>
    .panel-body {
        height: 480px;
        overflow: auto;
    }

    .media-object.img-circle {
        width: 64px;
        height: 64px;
    }

    .img-circle {
        width: 48px;
        height: 48px;
    }

    .time {
        float: right;
    }

    .media {
        margin-top: 24px;
    }
</style>
