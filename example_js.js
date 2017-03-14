var channel = pusher.subscribe('comment_chanel');


channel.bind('add_comment', function(data) {
    console.log(data.user_id);
    if(data.user_id == user_id){
        var item = '<li class="in">';
    } else {
        var item = '<li class="out">';
    }
    item += '<img class="avatar" alt="" src="/assets/images/avatar/'+data.avatar+'"/>';
    item += '<div class="message" id="dialog_message_'+user_id+'"></div>';
    item += '<div class="message">';
    item += '<span class="arrow"></span>';
    item += '<a href="#" class="name">'+data.surname+'</a>';
    item += '<span class="datetime"> at '+data.date+' </span>';
    item += '<span class="body">'+data.text+'</span>';
    item += '</div>';
    item += '</li>';
    $('#new_messages').prepend(item);
    $('#message_text_'+user_id).val('');
});


channel.bind('comment', function(data) {
    console.log(data);
    $('#blockDisplayAjax').hide();
    $('#blockDisplayAjaxLoading').hide();
    var date = new Date();
    var mon = ('0'+(1+date.getMonth())).replace(/.?(\d{2})/,'$1');
    var a = date.toString().replace(/^[^\s]+\s([^\s]+)\s([^\s]+)\s([^\s]+)\s([^\s]+)\s.*$/ig,' $4, $2.'+mon+'.$3');
    var item = '<div id="comment_'+data.id+'" style="border: 1px solid black;padding: 25px 25px 25px;background: #ffffff;margin-bottom: 37px;">';
    item += '<p id="tag_'+data.id+'">Комментарий № '+data.id+'</p>';
    item += '<p id="employee_'+data.id+'" style="color:#000000;margin-top: 15px;">'+data.surname + " " + data.name.slice(0, 1) + "." + data.middlename.slice(0, 1)+ ". (" + (data.login) + ")" + ' ' + a +'</p>';
    item += '<p style="margin-left: 29px;color:#000000;">'+data.text+'</p>';
    if(data.files.split('.')[1] == 'jpeg' || data.files.split('.')[1] == 'png' || data.files.split('.')[1] == 'jpg'){
        item += '<div style="margin-bottom: 20px;"><img class="task_image" style="width: 150px; height: 150px;" src="/assets/tasks_documents/'+data.files+'"></div>';
    } else {
        if(data.files != ''){
            for(var n = 0; n < data.images_all.length; n++){
                item += '<div style="margin-bottom: 20px;"><i class="fa fa-paperclip"></i> <a href="/multitasking/file_force_download/'+data.images_all[n]+'">'+data.images_all[n]+'</a></div>';
            }
            //item += '<div style="margin-bottom: 20px;"><i class="fa fa-paperclip"></i> <a href="/multitasking/file_force_download/'+data.files+'">'+data.files+'</a></div>';
        }
    }
    item += '</div>';
    $('#result').prepend(item);
    $('#comment_'+data.user_id).val('');
    $('#document_'+data.user_id).val('');
});



channel.bind('comment_close_task', function(data) {
    $('#blockDisplayAjax').hide();
    $('#blockDisplayAjaxLoading').hide();
    var date = new Date();
    var mon = ('0'+(1+date.getMonth())).replace(/.?(\d{2})/,'$1');
    var a = date.toString().replace(/^[^\s]+\s([^\s]+)\s([^\s]+)\s([^\s]+)\s([^\s]+)\s.*$/ig,' $4, $2.'+mon+'.$3');
    var item = '<div id="comment_'+data.id+'" style="border: 1px solid black;padding: 25px 25px 25px;background: #ffffff;margin-bottom: 37px;">';
    item += '<p id="tag_'+data.id+'">Комментарий № '+data.id+'</p>';
    item += '<p id="employee_'+data.id+'" style="color:#000000;margin-top: 15px;">'+data.surname + " " + data.name.slice(0, 1) + "." + data.middlename.slice(0, 1)+ ". (" + (data.login) + ")" + ' ' + a +'</p>';
    item += '<p style="margin-left: 29px;color:#000000;">'+data.text+'</p>';
    if(data.files.split('.')[1] == 'jpeg' || data.files.split('.')[1] == 'png' || data.files.split('.')[1] == 'jpg'){
        item += '<div style="margin-bottom: 20px;"><img class="task_image" style="width: 150px; height: 150px;" src="/assets/tasks_documents/'+data.files+'"></div>';
    } else {
        if(data.files != ''){
            item += '<div style="margin-bottom: 20px;"><i class="fa fa-paperclip"></i> <a href="/multitasking/file_force_download/'+data.files+'">'+data.files+'</a></div>';
        }
    }
    item += '</div>';
    $('#result').prepend(item);
    $('#comment_'+data.user_id).val('');
    $('#document_'+data.user_id).val('');
    $('#closeTaskUserButton').css('display', 'none');
    $('#resultTaskCloseUser').css('display', 'block');
});