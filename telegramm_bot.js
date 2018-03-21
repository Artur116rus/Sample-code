

/*
 *
 * Это код для общего просмотра. Запросы, токен и другое удалил.
 *
 * Бот для телеграмма, которе заменяет приложение. Как работает:
 * Когда заходишь в приложение, выходит ссылка на авторизацию в боте. Пользователь переходит по ссылке, вводит логин и пароль.
 * После того, как успешно был авторизован, его обратно отправит в телеграмм, где он должен обновить информацию. После обновления информации
 * у пользователя появляется меню.
 *
 *
 * */


var TelegramBot = require('node-telegram-bot-api'),
    token = 'token',
    bot = new TelegramBot(token, { polling: true });

var _task= null;
var _search= null;
var _sendclose= null;
var _sendclosenumber= null;
var _getUserId= null;
var field = null;
var _solo;

function connectBd(){
    var mysql      = require('mysql');
    var connection = mysql.createConnection({
        host     : 'host',
        user     : 'user',
        password : 'password',
        database : 'bd'
    });
    return connection;
}

function strip(html) {
    return html.replace(/<.*?>/g, '');
}

function testSendMessage(chatId){
    bot.sendMessage(chatId, 'Успешно отправили на закрытие!');
}


var sendOrCloseTask = function sendOrCloseTasks(taks_id, status) {
    console.log(taks_id);
    if(status == 1) {
        var options = {
            reply_markup: JSON.stringify({
                inline_keyboard: [
                    [{text: 'Написать комментарий', callback_data: 'sendComment_' + taks_id}, {text: 'Отправить на закрытие', callback_data: 'sendClose_'+taks_id}]
                ]
            })
        };
    } else if(status == 2){
        var options = {
            reply_markup: JSON.stringify({
                inline_keyboard: [
                    [{text: 'Написать комментарий', callback_data: 'sendComment_' + taks_id}]
                ]
            })
        };
    }
    return options;
};


bot.on('message', function(msg) {
    var chatId = msg.from.id;
    var last_name = msg.chat.last_name;
    var name = msg.chat.first_name;
    console.log(msg);

    //bot.sendMessage({chat_id: chatId,text: 'Some text...', reply_markup: JSON.stringify({hide_keyboard: true})});

    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if (results == '') {
            bot.sendMessage(chatId, 'Здравствуйте, авторизуйтесь пожалуйста\n После авторизации вас вернет обратно в телеграмм, где вы должны в чате прописать компанду - "/info" \n Авторизация происходит через сайт - \n URL='+chatId+'', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Авторизация',callback_data: '1'}]]
                })
            });
            return false;
        }
    });
    connection.end();
    if(msg.text == '/обновить'){
        startTasks(chatId);
    } else if (msg.text == '/info') {
        startTasks(chatId);
    } else if(msg.text == '/start'){
        startTasks(chatId);
    } else if (msg.text == 'Информация ℹ') {
        info(chatId);
    } else if (msg.text == 'Мои задания 👤') {
        getMenuTasks(chatId);
    } else if (msg.text == 'Назад ⤴️') {
        startTasks(chatId);
    } else if (msg.text == 'Вx->Текущие  📃') {
        var connection = connectBd();
        connection.connect();
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            if (results != '') {
                tasksVxTek(chatId, results[0].load_task);
            } else {
                console.log('Увы');
            }
        });
        connection.end();
    } else if (msg.text == 'Поиск 🔎') {
        var connection = connectBd();
        connection.connect();
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            if (results != '') {
                selectField(chatId);
            } else {
                console.log('Увы');
            }
        });
        connection.end();
        //checkValid(chatId);
    } else if (msg.text == 'Вx->Выполн.  📃') {
        tasksVxVipol(chatId, last_name, name);
    } else if (msg.text == 'Вx->Закрытые  📃') {
        tasksVxClose(chatId, last_name, name);
    } else if (msg.text == 'Исх->Текущие  📃') {
        var connection = connectBd();
        connection.connect();
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            if (results != '') {
                tasksIsxAction(chatId, results[0].load_task);
            } else {
                console.log('Увы');
            }
        });
        connection.end();
    } else if (msg.text == 'Исх->Выполн.  📃') {
        tasksIsxVipol(chatId, last_name, name);
    } else if (msg.text == 'Исх->Закрытые  📃') {
        tasksIsxClose(chatId, last_name, name);
    } else if (msg.text == '/alert') {
        viewSecondMessage(chatId, msg.text);
    }  else if(msg.text == 'Новости 📢'){
        newsKSYZ(chatId, msg);
    } else if(msg.text == 'Выйти ►'){
        logOut(chatId);
    } else if(msg.text == 'Отправить на закрытие 📃'){
        field = 'sendclose';
        checkValid(chatId);
    } else if(msg.text == 'Конечно'){
        updateStatusSendCLose(chatId, _sendclosenumber);
    } else if(msg.text == 'Обновить бота❗️'){
        startTasks(chatId);
    } else if(msg.text == 'Загрузить еще'){
        newLoadTask(chatId);
    } else if(msg.text == 'К заданиям ⤴️'){
        getMenuTasks(chatId);
    } else if (msg.text == 'Добавить задание 💾') {
        field = 'task';
        checkValid(chatId);
    } else if (checkValid != null) {
        var connection = connectBd();
        connection.connect();
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            if (results != '') {
                checkValid(chatId,results[0].field, msg.text, msg);
                checkValid(chatId, results[0].field);
            } else {
                bot.sendMessage(chatId, 'Ошибка!');
            }
        });
        connection.end();
    }
    else {
        defaultCommand(chatId);
    }
})

bot.on('callback_query', function (msg) {
    var chatId = msg.from.id;
    var text = msg.data;
    var comand = text.split('_');
    console.log(comand[0]);
    if(comand[0] == 'sendComment'){
        var connection = connectBd();
        connection.connect();

        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
        });

        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            if (results != '') {
                selectField(chatId, comand[1]);
            } else {
                console.log('Увы');
            }
        });
        connection.end();
    } else if(comand[0] == 'sendClose'){
        console.log('Отпарвляем на закрытие');
        console.log(comand[1]);
        bot.sendMessage(chatId, 'Подождите. Ваша информация обрабатывается...');

        const https = require('https');

        var req = https.get('URL', (res) => {
                console.log('statusCode:', res.statusCode);
        console.log('headers:', res.headers);


        res.on('data', (d) => {
            process.stdout.write(d);
        bot.sendMessage(chatId, 'Успешно отправили на закрытие!');
        startTasks(chatId);
    });

    }).on('error', (e) => {
            console.error(e);
    });
        req.end();
    }
});

function newLoadTask(chatId){
    const https = require('https');

    var req = https.get('URL', (res) => {
            console.log('statusCode:', res.statusCode);
    console.log('headers:', res.headers);


    res.on('data', (d) => {
        process.stdout.write(d);
    getLoadPage(chatId);
});

}).on('error', (e) => {
        console.error(e);
});
    req.end();
}

function getLoadPage(chatId){
    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        if (results != '') {
            if(results[0].type_task == 1){
                tasksVxTek(chatId, results[0].load_task)
            } else if(results[0].type_task == 4){
                tasksIsxAction(chatId, results[0].load_task);
            }
        } else {
            bot.sendMessage(chatId, 'Ошибка!');
        }
    });
    connection.end();
}

function newsKSYZ(chatId){
    bot.sendMessage(chatId, 'Тут будут новости компаний');
}

function selectField(chatId, text, msg){
    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        if (results != '') {
            console.log('Ya_tyt '+results[0].field);
            checkValid(chatId, results[0].field);
        } else {
            bot.sendMessage(chatId, 'Ошибка!');
        }
    });
    connection.end();
}

function viewSecondMessage(chatId, text){
    bot.sendMessage(chatId, 'Введите текст задачи');
}


function authMes(chatId){
    bot.sendMessage(chatId, 'Здравствуйте, авторизуйтесь пожалуйста\n После авторизации вас вернет обратно в телеграмм, где вы должны в чате прописать компанду - "/info" \n Авторизация происходит через сайт - \n URL='+chatId+'', {
        reply_markup: JSON.stringify({
            keyboard: [
                [{text: 'Авторизация',callback_data: '1'}]]
        })
    });
}

function logOut(chatId){
    const https = require('https');

    var req = https.get('SQL query', (res) => {
            console.log('statusCode:', res.statusCode);
    console.log('headers:', res.headers);


    res.on('data', (d) => {
        process.stdout.write(d);
        authMes(chatId);
    });

    }).on('error', (e) => {
            console.error(e);
    });
        req.end();
}

function startTasks(chatId){
    var connection = connectBd();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
    });
    connection.end();
    bot.sendMessage(chatId, 'Здравствуйте, вас приветствует бот Учета задания.\n /info - начало работ', {
        reply_markup: JSON.stringify({
            keyboard: [
                [{text: 'Мои задания 👤',callback_data: '1'},{text: 'Поиск 🔎',callback_data: '1'}],
                [{text: 'Информация ℹ',callback_data: '2'}, {text: 'Обновить бота❗️',callback_data: '2'}],
                [{text: ' Новости 📢',callback_data: '223'}, {text: 'Выйти ►',callback_data: '2'}]]
        })
    });
}

function info(chatId){
    bot.sendMessage(chatId, 'Здравствуйте, вас приветствует бот по системе учета задний!\n Используйте меню для получения ответа на ваши вопросы!\n Вам в телеграмм будут приходить следующие уведомления:\n 1) О добавление нового задания\n 2) О новых комментариях в ваших заданиях\n 3) О закрытие задания\n 4) О отправке на закрытие');
}

function getMenuTasks(chatId){
    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
    });
    connection.end();
    bot.sendMessage(chatId, 'Выберите тип задания', {
        reply_markup: JSON.stringify({
            keyboard: [
                [{text: 'Вx->Текущие  📃',callback_data: '1'},{text: 'Вx->Выполн.  📃',callback_data: '1'},{text: 'Вx->Закрытые  📃',callback_data: '1'}],
                [{text: 'Исх->Текущие  📃',callback_data: '1'},{text: 'Исх->Выполн.  📃',callback_data: '1'},{text: 'Исх->Закрытые  📃',callback_data: '1'}],
                [{text: 'Назад ⤴️',callback_data: '2'}]]
        })
    });
}

function updateTelegramIdUser(user_id, chatId){
    //console.log(user_id);return false;
    var connection = connectBd();

    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if (results != '') {
            bot.sendMessage(chatId, 'Вы успешно авторизованы!');
        } else {
            bot.sendMessage(chatId, 'Возникли ошибки!');
        }
    });
    connection.end();
}

function checkValid(chatId, field, text, msg) {
    if (field == 'task') {
        if (text) {
            InsertTask(chatId, text, msg);
        } else {
            bot.sendMessage(chatId, 'Введите текст задания', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Назад ⤴️'}]]
                })
            });
            //bot.sendMessage(chatId, 'Введите текст задачи');
        }
    }

    if (field == 'search') {
        if (text) {
            searchTasks(chatId, text, msg);
        }
        else {
            bot.sendMessage(chatId, 'Поиск осуществляется по моим заданиям.\n Введите текст для поиска...', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Назад ⤴️'}]]
                })
            });
            //bot.sendMessage(chatId, 'Введите текст задачи');
        }
    }

    if (field == 'sendclose') {
        if (text) {
            sendclosetask(chatId, text, msg);
        }
        else {
            bot.sendMessage(chatId, 'Введите номер задания для отправки задчи на закрытие.', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Назад ⤴️'}]]
                })
            });
            //bot.sendMessage(chatId, 'Введите текст задачи');
        }
    }

    if (field == 'comment') {
        if (text) {
            addComment(chatId, text, msg);
        }
        else {
            bot.sendMessage(chatId, 'Введите текст комментария.', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Назад ⤴️'}]]
                })
            });
            //bot.sendMessage(chatId, 'Введите текст задачи');
        }
    }
}

function addComment(chatId, text, msg){
    console.log(text);
    bot.sendMessage(chatId, 'Подождите. Ваша информация обрабатывается...');
    var connection = connectBd();
    connection.query('SQL query', function (error, results, fields) {
        if (error){
            error
        } else {
            const https = require('https');

            var req = https.get('URL', (res) => {
                    console.log('statusCode:', res.statusCode);
            console.log('headers:', res.headers);


            res.on('data', (d) => {
                process.stdout.write(d);
            startTasks(chatId);
        });

        }).on('error', (e) => {
                console.error(e);
        });
            req.end();
        }
    });
    connection.end();
}

function sendclosetask(chatId, text, msg){
    var num = Number(text);
    console.log(num);
    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if (results != '') {
            for (var i = 0; i < results.length; i++) {
                bot.sendMessage(chatId, 'Эту задачу закрыть?\n Задача №' + results[i].id + '\n Описание: ' + results[i].full + '\n ссылка на задание (URL' + results[i].id + ')', {
                    reply_markup: JSON.stringify({
                        keyboard: [
                            [{text: 'Конечно'},{text: 'Нееет'}]]
                    })
                });
            }
            _sendclosenumber = num;
            field = null;
            _sendclose = null;
        } else {
            bot.sendMessage(chatId, 'Нет такой задачи');
        }
    });
    connection.end();
}

function updateStatusSendCLose(chatId, _sendclosenumber){
    console.log('get_'+_sendclosenumber);
    var connection = connectBd();
    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            _getUserId = results[0].user_id;
        } else {
            bot.sendMessage(chatId, 'Пользователь не найден!');
        }
    });
    //console.log(getUserID);
    if(typeof getUserID == 'undefined'){
        startTasks(chatId);return false;
    } else {
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            console.log(results);
            if (results != '') {
                bot.sendMessage(chatId, 'Задача отправлена на закрытие. Поздравляю!');
                getUserID = null;
            } else {
                bot.sendMessage(chatId, 'Возникли ошибки');
            }
        });

        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            console.log(results);
            if (results != '') {
                bot.sendMessage(chatId, 'Задача отправлена на закрытие. Поздравляю!');
            } else {
                bot.sendMessage(chatId, 'Возникли ошибки');
            }
        });
    }
    connection.end();
}

function searchTasks(chatId, text, msg){
    console.log('Ищем...');
    if(text != '') {
        var last_name = msg.chat.last_name;
        var name = msg.chat.first_name;
        var connection = connectBd();

        connection.connect();

        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            console.log(results);
            if (results != '') {
                for (var i = 0; i < results.length; i++) {
                    bot.sendMessage(chatId, 'Задача №' + results[i].id + '\n Описание: ' + strip(results[i].full) + '\n ссылка на задание (URL' + results[i].id + ') Дополнительные опции к заданию: ', sendOrCloseTask(results[i].id, results[i].status));
                }
            } else {
                bot.sendMessage(chatId, 'У вас нет задач!');
            }
        });
        connection.end();
    }
}

function InsertTask(chatId, text, msg) {
    //console.log(text);return;
    if(text != 'Назад ⤴️') {
        var connection = connectBd();
        var first_name = msg.chat.first_name;
        var last_name = msg.chat.last_name;
        var date = msg.date;
        connection.connect();
        connection.query('SQL query', function (error, results, fields) {
            if (error) throw error;
            console.log(results);
            if (results = 1) {
                bot.sendMessage(chatId, 'Вы создали новую задачу.');
                _task = null;
                field = null;
                startTasks(chatId);
                return;
            } else {
                bot.sendMessage(chatId, 'Ошибка!');
                return;
            }
        });
        connection.end();
    } else {
        startTasks(chatId);
    }
}

function tasksVxTek(chatId, load_task){
    var connection = connectBd();
    connection.connect();
    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
    });

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            for(var i = 0; i < results.length; i++){
                bot.sendMessage(chatId, 'Задача №' + results[i].id + '\n Описание: ' + strip(results[i].full) + '\n ссылка на задание (URL) Дополнительные опции к заданию: ', sendOrCloseTask(results[i].id, results[i].status));
            }
            bot.sendMessage(chatId, 'Еще?', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Загрузить еще'},{text: 'К заданиям ⤴️', callback_data: 'all_my_task'}]]
                })
            });
        } else {
            bot.sendMessage(chatId, 'У вас нет задач!');
        }
    });
    connection.end();
    //bot.sendMessage(chatId, 'Если задача выполнена, то ее можно закрыть, написав в чате (пример): 6032 - готово, где 6032 - это номер задания');
}

function tasksVxVipol(chatId, last_name, name){
    var connection = connectBd();

    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            for(var i = 0; i < results.length; i++){
                bot.sendMessage(chatId, 'Задача №'+results[i].id+'\n Описание: '+results[i].full+'\n ссылка на задание (URL/'+results[i].id+')');
            }
        } else {
            bot.sendMessage(chatId, 'У вас нет задач!');
        }
    });
    connection.end();
    //bot.sendMessage(chatId, 'Если задача выполнена, то ее можно закрыть, написав в чате (пример): 6032 - готово, где 6032 - это номер задания');
}

function tasksVxClose(chatId, last_name, name){
    var connection = connectBd();

    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            for(var i = 0; i < results.length; i++){
                bot.sendMessage(chatId, 'Задача №'+results[i].id+'\n Описание: '+results[i].full+'\n ссылка на задание (URL'+results[i].id+')');
            }
        } else {
            bot.sendMessage(chatId, 'У вас нет задач!');
        }
    });
    connection.end();
    //bot.sendMessage(chatId, 'Если задача выполнена, то ее можно закрыть, написав в чате (пример): 6032 - готово, где 6032 - это номер задания');
}

function tasksIsxAction(chatId, load_task) {
    var connection = connectBd();
    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
    });

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            for(var i = 0; i < results.length; i++){
                bot.sendMessage(chatId, 'Задача №'+results[i].id+'\n Описание: '+strip(results[i].full)+'\n ссылка на задание (URL'+results[i].id+')');
            }
            bot.sendMessage(chatId, 'Еще?', {
                reply_markup: JSON.stringify({
                    keyboard: [
                        [{text: 'Загрузить еще'},{text: 'К заданиям ⤴️', callback_data: 'all_my_task'}]]
                })
            });
        } else {
            bot.sendMessage(chatId, 'У вас нет задач!');
        }
    });
    connection.end();
    //bot.sendMessage(chatId, 'Если задача выполнена, то ее можно закрыть, написав в чате (пример): 6032 - готово, где 6032 - это номер задания');
}

function tasksIsxVipol(chatId){
    var connection = connectBd();

    connection.connect();

    connection.query('SQL query', function (error, results, fields) {
        if (error) throw error;
        console.log(results);
        if(results != ''){
            for(var i = 0; i < results.length; i++){
                bot.sendMessage(chatId, 'Задача №'+results[i].id+'\n Описание: '+results[i].full+'\n ссылка на задание (URL'+results[i].id+')');
            }
        } else {
            bot.sendMessage(chatId, 'У вас нет задач!');
        }
    });
    connection.end();
}

function tasksIsxClose(chatId){
    bot.sendMessage(chatId, 'Тут будут Исходящие-Закрытые');
}

function defaultCommand(chatId) {
    bot.sendMessage(chatId, 'Это так себе)');
}