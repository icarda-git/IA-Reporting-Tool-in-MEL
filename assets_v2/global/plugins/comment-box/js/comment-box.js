const comment_box = {
    initialized: false,
    shareWith: [],
    HasReviewCommentLink: null,
    GetAllReviewCommentsDataLink: null,
    GetReviewCommentDataLink: null,
    SubmitReviewCommentLink: null,
    DeleteReviewCommentLink: null,
    init: (links, shareWith) => {
        if (links) {
            comment_box.HasReviewCommentLink = links.hasOwnProperty('HasReviewCommentLink') ? links.HasReviewCommentLink : null;
            comment_box.GetAllReviewCommentsDataLink = links.hasOwnProperty('GetAllReviewCommentsDataLink') ? links.GetAllReviewCommentsDataLink : null;
            comment_box.GetReviewCommentDataLink = links.hasOwnProperty('GetReviewCommentDataLink') ? links.GetReviewCommentDataLink : null;
            comment_box.SubmitReviewCommentLink = links.hasOwnProperty('SubmitReviewCommentLink') ? links.SubmitReviewCommentLink : null;
            comment_box.DeleteReviewCommentLink = links.hasOwnProperty('DeleteReviewCommentLink') ? links.DeleteReviewCommentLink : null;
        }
        comment_box.shareWith = shareWith;

        if (!comment_box.initialized) {
            $('body')
                .on('shown.bs.modal', '.modal', function () {
                    const modal_scrollable = $('.modal-scrollable');
                    if (modal_scrollable.length > 0) {
                        modal_scrollable.unbind('scroll');
                        modal_scrollable.scroll(function () {
                            comment_box.reposition_onscroll(modal_scrollable);
                        });
                    }
                    comment_box.reposition_onscroll($(this));
                    comment_box.has_comments($(this).find('form'));
                })
                .on('click', '.add-comment', function () {
                    const form = $(this).parents('form:first');
                    const form_id = form.attr('id');
                    const item_id = form.data('item_id');

                    let target_element = $(this).next('input.commentable, textarea.commentable, select.commentable').eq(0);
                    if (target_element.length === 0 && $(this).parent('.radio-list:first, .checkbox-list:first').length > 0)
                        target_element = $(this).parent('.radio-list:first, .checkbox-list:first').find('input.commentable:first');

                    comment_box.add_box(target_element, form_id, item_id);
                })
                .on('click', '*', function (e) {
                    const target = $(e.target);
                    if (target.hasClass('comments-box') || target.parents('.comments-box').length > 0)
                        return;
                    comment_box.close_comments_box();
                })
                .on('click', '.btn-add-comment', function () {
                    comment_box.add_comment($(this));
                })
                .on('click', '.btn-edit-comment', function () {
                    comment_box.update_comment($(this));
                })
                .on('click', '.btn-discard-comment', function () {
                    comment_box.discard_comment($(this));
                })
                .on('click', '.btn-close-comment', function () {
                    comment_box.close_comments_box($(this));
                })
                .on('click', '.btn-delete-comment', function () {
                    comment_box.delete_comment($(this));
                })
                .on('click', '.expand-comment-box', function (e) {
                    $(this).parents('.comments-box:first').addClass('expanded');
                })
                .on('click', '.collapse-comment-box', function (e) {
                    $(this).parents('.comments-box:first').removeClass('expanded');
                    comment_box.reposition_onscroll($(this));
                });

            let window_onscroll = window.onscroll;
            window.onscroll = function () {
                comment_box.reposition_onscroll($('body'));
                if (window_onscroll != null && $.isFunction(window_onscroll))
                    window_onscroll();
            };
            comment_box.initialized = true;
        }
    },
    reload: (box) => {
        if (typeof box === typeof undefined || box.length === 0)
            box = $('.popover.comments-box');

        if (box.length === 0)
            return;

        comment_box.add_box(box.data('target_element'), box.data('form_id'), box.data('item_id'));
    },
    add_box: (target_element, form_id, item_id) => {
        if (target_element.length === 0 || comment_box.GetAllReviewCommentsDataLink == null)
            return;

        $('.popover.comments-box .btn-close-comment').click();
        $.ajax({
            url: comment_box.GetAllReviewCommentsDataLink,
            type: 'post',
            data: {
                form_id: form_id,
                item_id: item_id,
                field_name: target_element.attr('name')
            },
            success: function (data, status) {
                if (data.result === false) {
                    App.alert({
                        type: 'danger',  // alert's type
                        message: 'Oops! something went wrong.',  // alert's message
                        closeInSeconds: 5

                    });
                    return;
                }

                let box_html = comment_box.create_box_html(data.data);
                box_html.data('target_element', target_element);
                box_html.find('.popover-content');

                box_html.data('form_id', form_id);
                box_html.data('item_id', item_id);

                box_html = comment_box.position_box(box_html);
                $('body').append(box_html);
                box_html.find('input:checkbox').uniform();
                box_html.show().find('.popovers').popover();
            },
            error: function (xhr, desc, err) {
                App.alert({
                    type: 'danger',  // alert's type
                    message: 'Oops! something went wrong.',  // alert's message
                    closeInSeconds: 5

                });
            }
        });
    },
    position_box: (box) => {
        if (typeof box === typeof undefined || box.length === 0)
            box = $('.popover.comments-box');

        if (box.length === 0)
            return;

        let target_element = box.data('target_element');
        target_element = target_element.is(':visible') ? target_element : target_element.parent();
        const offset = target_element.offset();
        const left = offset.left;
        let top = offset.top - 233;
        if (top - $(window).scrollTop() <= 0) {
            box.removeClass('top').addClass('bottom');
            top = offset.top + target_element.height() + 20;
        } else {
            box.removeClass('bottom').addClass('top');
        }

        box.css('top', top);
        if (!box.hasClass('expanded'))
            box.css('left', left);

        return box;
    },
    create_comment_html: (comment) => {
        let comment_html = $('<div class="comment-box">' +
            '    <label class="control-label comment-info">' +
            '        <b>' + comment.commented_by + '</b>' +
            '        <small class="comment-date">' +
            '            <i>' + comment.modified_date + '</i>' +
            '        </small>' +
            '    </label>' +
            '    <div class="comment-content">' + comment.comment + '</div>' +
            '    <div class="comment-tool-box">' +
            '        <small>' +
            '            <a class="btn-edit-comment">Edit' +
            '            </a> -' +
            '            <a class="btn-delete-comment">Delete</a>' +
            '        </small>' +
            '    </div>' +
            '</div>');

        const history = comment_box.create_history_html(comment.history);
        comment_html.find('.comment-date').prepend(history);

        let shared_with = [];
        if (comment.user_groups != null && comment.user_groups.length > 0) {
            if (!Array.isArray(comment.user_groups))
                comment.user_groups = comment.user_groups.split(',');

            shared_with = comment_box.shareWith.filter(shareWith => comment.user_groups.indexOf(shareWith.group_id.toString()) !== -1);
            shared_with = shared_with.map(shareWith => shareWith.label);
        }
        comment_html.find('.comment-info').prepend('<i class="fa fa-users popovers' + (shared_with.length === 0 ? ' not_shared' : '') + '" data-trigger="hover" data-container="body" data-placement="top" data-content="Shared with: ' + (shared_with.length === 0 ? 'Only you' : shared_with.join(', ')) + '"></i> ');

        if (parseInt(comment.is_deleted) === 1)
            comment_html.addClass('deleted-comment')
                .find('.comment-tool-box, .comment-history').remove();
        if (parseInt(comment.comment_owner) !== 1)
            comment_html.find('.comment-tool-box').remove();

        comment_html.find('.btn-edit-comment, .btn-delete-comment').data('comment_id', comment.comment_id);
        return comment_html;
    },
    create_comment_area_html: (type) => {
        const html = $('<div class="comment-box">' +
            '    <div class="comment-box-textarea">' +
            '        <span class="btn-discard-comment"><i class="fa fa-times"></i></span>' +
            '        <textarea class="form-control" rows="3" name="comment_content" placeholder="Add a comment"></textarea>' +
            '    </div>' +
            '    <div class="btn-group comment-tool-box">' +
            '        <button class="btn btn-default btn-sm btn-add-comment">' +
            '                ' + type + ' Comment' +
            '        </button>' +
            '     </div>' +
            '</div>');

        let checkboxs = $('<label class="control-label is_public"><div class="checkbox-list"></div></label>');
        $.each(comment_box.shareWith, function (index, value) {
            let checkbox = '<label class="checkbox-inline">' +
                '    <input type="checkbox" name="user_groups[]" value="' + value.group_id + '" ' +
                (value.default ? 'checked' : '') + ' ' + (value.disabled ? 'disabled' : '') + '>' +
                value.label +
                '</label>';

            checkbox = $(checkbox);
            if (value.popover)
                checkbox.addClass('popovers')
                    .attr('data-trigger', 'hover')
                    .attr('data-container', 'body')
                    .attr('data-placement', 'top')
                    .attr('data-content', value.popover);

            checkboxs.find('.checkbox-list').append(checkbox);
        });

        if (checkboxs.find('.checkbox-inline').length > 0)
            html.find('.comment-tool-box').prepend(checkboxs);
        return html;
    },
    create_history_html: (history_comments) => {
        if (history_comments.length === 0)
            return $();

        //Temporary until we figure out how to show history!
        return $('<i class="fa fa-history btn-history-comment popovers"' +
            '       data-trigger="hover" data-placement="top"' +
            '       data-content="Modified"></i>');

        let html = $('<div class="dropdown comment-history">' +
            '    <i class="fa fa-history btn-history-comment popovers dropdown-toggle"' +
            '       data-trigger="hover" data-placement="top"' +
            '       data-content="Show history" data-toggle="dropdown"></i>' +
            '    <ul class="dropdown-menu">' +
            '    </ul>' +
            '</div>');

        for (let i = 0; i < history_comments.length; i++)
            html.find('.dropdown-menu').append('<li><label><small class="comment-date"><i>' + history_comments[i].created_date + '</i></small></label>' + history_comments[i].comment + '</li>');
        return html;
    },
    create_box_html: (comments) => {
        let comments_html = $();
        if (comments.length === 0) {
            comments_html = $('<div class="no-comments">No Comments <i class="fa fa-comment-o"></i></div>');
        } else {
            for (let i = 0; i < comments.length; i++)
                comments_html = $(comments_html).add(comment_box.create_comment_html(comments[i]));
        }

        let html = $('<div class="comments-box popover fade top in">' +
            '    <div class="arrow"></div>' +
            '    <h3 class="popover-title"> ' +
            '       <i class="fa fa-arrows-alt popovers expand-comment-box" data-trigger="hover" data-container="body" data-placement="right" data-content="Expand"></i>' +
            '       <i class="fa fa-compress popovers collapse-comment-box" data-trigger="hover" data-container="body" data-placement="right" data-content="Collapse"></i>' +
            '       Comments' +
            '       <span class="btn-close-comment">' +
            '           <i class="fa fa-times"></i>' +
            '       </span>' +
            '    </h3>' +
            '    <div class="popover-content">' +
            '    </div>' +
            '</div>');
        html.find('.popover-content').append(comment_box.create_comment_area_html('Add'));
        html.find('.popover-content').append(comments_html);
        return html;
    },
    add_comment_button_reposition: (parent) => {
        parent.find('input.commentable, textarea.commentable, select.commentable').each(function () {
            let element = $(this).prev('.add-comment');
            let is_radio_or_checkbox = $(this).is('input[type=radio], input[type=checkbox]');
            if (is_radio_or_checkbox)
                element = $(this).parents('.radio-list:first, .checkbox-list:first').find('.add-comment');

            if (element.hasClass('ignore_commentable')) {
                element.css('display', 'none');
                return;
            }

            let position = {
                top: 0,
                left: 0
            };

            if (is_radio_or_checkbox) {
                let element_parent = $(this).parents('.radio-inline:first, .checkbox-inline:first');
                if (element_parent.length > 0) {
                    position = element_parent.position();
                    if (element_parent.find(':first-child').length > 0)
                        position.top += element_parent.find(':first-child').position().top;
                }
            } else {
                position = $(this).position();
            }

            if (!$(this).is(':visible') && $(this).parent().is(':visible'))
                position = $(this).parent().hasClass('input-group') ? $(this).parent().parent().position() : $(this).parent().position();

            element
                .css('display', ($(this).is(':visible') || $(this).parent().is(':visible') ? '' : 'none'))
                .css('top', position.top)
                .css('left', (position.left - 25) + 'px');

            if (is_radio_or_checkbox)
                $(this).parents('.radio-list:first, .checkbox-list:first').append(element);
            else
                element.insertBefore($(this));
        });
    },
    window_onscroll_timeout: null,
    reposition_onscroll: (parent) => {
        if (comment_box.window_onscroll_timeout)
            clearTimeout(comment_box.window_onscroll_timeout);
        comment_box.window_onscroll_timeout = setTimeout(function () {
            comment_box.position_box();
            comment_box.add_comment_button_reposition(parent);
        }, 25);
    },
    add_comment: (element) => {
        if (comment_box.SubmitReviewCommentLink == null)
            return;
        const box = element.parents('.comment-box:first');
        const parent = box.parents('.comments-box:first');
        App.blockUI({
            target: parent,
            boxed: true
        });

        const textarea = box.find('textarea');
        let user_groups = [];
        box.find('input:checkbox[name="user_groups[]"]:checked').each(function () {
            user_groups.push($(this).val());
        });

        const comment = {
            form_id: parent.data('form_id'),
            item_id: parent.data('item_id'),
            field_name: parent.data('target_element').attr('name'),
            comment_id: textarea.data('comment_id'),
            comment: textarea.val(),
            user_groups: user_groups
        };
        if (comment.comment == null || comment.comment.trim() === '') {
            App.unblockUI(parent);
            return;
        }

        $.ajax({
            url: comment_box.SubmitReviewCommentLink,
            type: 'post',
            data: comment,
            success: function (data, status) {
                App.unblockUI(parent);
                if (data.result === false) {
                    App.alert({
                        type: 'danger',  // alert's type
                        message: data.message ? data.message : 'Oops! something went wrong.',  // alert's message
                        closeInSeconds: 5

                    });
                    return;
                }

                textarea.data('comment_id', null).val('');
                comment_box.has_comments($('#' + parent.data('form_id')));
                comment_box.reload();
            },
            error: function (xhr, desc, err) {
                App.alert({
                    type: 'danger',  // alert's type
                    message: 'Oops! something went wrong.',  // alert's message
                    closeInSeconds: 5

                });
                App.unblockUI(parent);
            }
        });
    },
    update_comment: (element) => {
        if (comment_box.GetReviewCommentDataLink == null)
            return;
        const box = element.parents('.comment-box:first');
        const parent = box.parents('.comments-box:first');
        App.blockUI({
            target: parent,
            boxed: true
        });

        $.ajax({
            url: comment_box.GetReviewCommentDataLink + '/comment_id/' + element.data('comment_id'),
            type: 'get',
            success: function (data, status) {
                App.unblockUI(parent);
                if (data.result === false) {
                    App.alert({
                        type: 'danger',  // alert's type
                        message: 'Oops! something went wrong.',  // alert's message
                        closeInSeconds: 5

                    });
                    return;
                }

                let comment_area_html = comment_box.create_comment_area_html('Update');

                box.find('.comment-content, .comment-tool-box').hide();
                comment_area_html.insertAfter(box.find('.comment-info'))
                    .fadeIn()
                    .find('textarea')
                    .data('comment_id', data.data.comment_id)
                    .val(data.data.comment);

                box.find('input:checkbox[name="user_groups[]"]').prop('checked', false);
                if (data.data.user_groups != null && data.data.user_groups.length > 0) {
                    if (!Array.isArray(data.data.user_groups))
                        data.data.user_groups = data.data.user_groups.split(',');
                    $.each(data.data.user_groups, function (index, value) {
                        box.find('input:checkbox[name="user_groups[]"][value="' + cleanStringValue(value) + '"]').prop('checked', true);
                    });
                }

                box.find('input:checkbox').uniform();
            },
            error: function (xhr, desc, err) {
                App.alert({
                    type: 'danger',  // alert's type
                    message: 'Oops! something went wrong.',  // alert's message
                    closeInSeconds: 5

                });
                App.unblockUI(parent);
            }
        });
    },
    discard_comment: (element) => {
        const box = element.parents('.comment-box:first');
        box.find('textarea').val('').data('comment_id', null);

        //in edit comment
        if (box.parent('.comment-box:first').length > 0) {
            box.fadeOut('', () => {
                box.parent('.comment-box:first').find('.comment-content, .comment-tool-box').show();
                box.remove();
            });
        }
    },
    close_comments_box: (element) => {
        let box = $('.comments-box');
        if (element && element.length > 0)
            box = element.parents('.comments-box:first');

        box.fadeOut('', () => {
            box.remove();
        });
    },
    delete_comment: (element) => {
        if (comment_box.DeleteReviewCommentLink == null)
            return;
        const box = element.parents('.comment-box:first');
        const parent = box.parents('.comments-box:first');
        App.blockUI({
            target: parent,
            boxed: true
        });

        $.ajax({
            url: comment_box.DeleteReviewCommentLink + '/comment_id/' + element.data('comment_id'),
            type: 'delete',
            success: function (data, status) {
                App.unblockUI(parent);
                if (data.result === false) {
                    App.alert({
                        type: 'danger',  // alert's type
                        message: 'Oops! something went wrong.',  // alert's message
                        closeInSeconds: 5
                    });
                    return;
                }
                comment_box.reload();
            },
            error: function (xhr, desc, err) {
                App.alert({
                    type: 'danger',  // alert's type
                    message: 'Oops! something went wrong.',  // alert's message
                    closeInSeconds: 5

                });
                App.unblockUI(parent);
            }
        });
    },
    has_comments: (form) => {
        if (form.length === 0 || form.find('.fa.add-comment').length === 0)
            return;

        form.find('.fa.add-comment').removeClass('fa-comments').addClass('fa-comments-o');
        $.ajax({
            url: comment_box.HasReviewCommentLink,
            type: 'post',
            data: {
                form_id: form.attr('id'),
                item_id: form.data('item_id')
            },
            success: function (data, status) {
                $.each(data.data, function (index, field_name) {
                    let element = form.find('[name="' + field_name + '"]');
                    let comment_button = element.prev('.fa.add-comment');

                    if (comment_button.length === 0 && element.is('input[type=radio], input[type=checkbox]'))
                        comment_button = element.parents('.radio-list:first, .checkbox-list:first').find('.fa.add-comment:first');

                    comment_button.addClass('fa-comments').removeClass('fa-comments-o');
                });
            }
        });
    }
};

