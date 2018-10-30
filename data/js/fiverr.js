(function($) {
    var model = '<span class="today_user_icon js-user">';
    model += '<img class="js-opacity js-pic" /><label class="today_user_label"><span class="js-pseudo">pagreifer</span> <span class="js-status">(Online since 31 minutes)</span></label>';
    model += '</span>';

    var cfg = {};
    cfg.first_time = 1;

    var get = function() {
        $.get('/admin/index.php?function=get_todays_users&json=1', init);
    };

    var getByPseudo = function(pseudo, list) {
        var l = list.length;
        for (var i = 0; i < l; i++) {
            if (list[i].pseudo == pseudo)
                return list[i];
        }
        return false;
    };

    var init = function(res) {
        model = $(model)[0];
        var t = JSON.parse(res), l = t.length;

        var users = [];
        for (var i = 0; i < l; i++) {
            var user = {};
            var data = ['pseudo', 'last_time', 'image', 'is_online'];
            var ll = data.length;
            for (var j = 0; j < ll; j++) {
                user[data[j]] = t[i][j];
            }
            user.is_online = user.is_online == 'online';
            user.last_time = new Date(user.last_time);
            users.push(user);
        }

        users.reverse();
        if (users.length == 0)
            return;

        var min_time = users[0].last_time /* get the live time with PHP here instead of the max_time : <?php echo time() * 1000; ?> */;
        var max_time = users[users.length - 1].last_time;
        users.reverse();
        var l = users.length;

        if (cfg.first_time) {
            // we get all the users in the list, that's all good already

        } else {
            // we get all the users in the list
            // if they exists in the old list AND in the new one, we update the data
            // if they exists in the old one only, we remove them nicely/with animation
            // if they exists in the new one only, we add it nicely/with animation
            var new_list = [];

            var l = users.length;
            for (var i = 0; i < l; i++) {
                var old_data = getByPseudo(users[i].pseudo, cfg.users);

                // does not exists yet
                if (!old_data)
                    users[i].is_new = 1;
            }

            var l = cfg.users.length;
            for (var i = 0; i < l; i++) {
                var new_data = getByPseudo(cfg.users[i].pseudo, users);

                // removed
                if (!new_data) {
                    if (cfg.users[i].to_remove)
                        continue; // already removed
                    cfg.users[i].to_remove = 1;
                    delete cfg.users[i].is_new;
                    users.push(cfg.users[i]);
                }
            }
        }

        users.sort(function(a, b) {
            return b.last_time - a.last_time;
        });

        var wrapper = document.querySelector('#todays_users');
        wrapper.innerHTML = '';
        var l = users.length;
        for (var i = 0; i < l; i++) {
            var user = users[i];
            var status;
            if (user.is_online)
                status = '(Online since ' + format(max_time - user.last_time) + ')';
            else
                status = '(' + format(max_time - user.last_time) + ' ago)';

            var line = model.cloneNode(true);
            line.querySelector('.js-pseudo').textContent = user.pseudo;
            if (user.is_online)
                line.className += ' online_user';
            else
                line.className += ' offline_user';
            line.querySelector('.js-pic').src = user.image;
            var opacity = (user.last_time - min_time) / (max_time - min_time);
            if (opacity < 0.2)
                opacity = 0.2;
            line.querySelector('.js-opacity').style.opacity = opacity;
            line.querySelector('.js-status').textContent = status;
            //user.is_new = 1;
            if (user.is_new) {
                line.style.height = '0px';
                line.style.overflow = 'hidden';
                line.style.display = 'inline-block';
            }
            if (user.to_remove) {
                line.style.height = '20px';
                line.style.overflow = 'hidden';
                line.style.display = 'inline-block';
            }

            wrapper.appendChild(line);
            if (user.is_new) {
                $(line).animate({height: '20px'}, function() {
                    $(this)[0].style.overflow = 'visible';
                });
            }
            if (user.to_remove) {
                $(line).animate({height: '0px'});
            }
        }

        cfg.first_time = 0;
        cfg.users = users;
    };

    var format = function(ms) {
        var sec = parseInt(ms, 10);
        if (sec < 60) {
            return sec + ' sec';
        } else if (sec < 60 * 60) {
            return parseInt(sec / 60, 10) + ' min';
        } else {
            var nb_h = parseInt(sec / (60 * 60), 10);
            return nb_h + ' hours, ' + format((sec - nb_h * 60 * 60));
        }
    };

    var style = document.createElement('style');
    style.textContent = '#todays_users { top:0px; } .today_user_label { top:-2px; }';
    document.body.appendChild(style);
    get();
    setInterval(get, 10000);
})(jQuery);