const ReviewTableManaged = function () {

    const initIaReportTable = function () {
        let ia_report_table = $('#ia_report_table');
        ia_report_table.data('ajax_url', GetAllIaReportsToReviewDataLink);
        ia_report_table.dataTable({
            "sAjaxSource": GetAllIaReportsToReviewDataLink,
            "sAjaxDataProp": "data",
            "columns": [{
                "mData": "ia_report_id",
                "width": "25px"
            }, {
                "mData": "reporting_year",
                "width": "30px"
            }, {
                "mData": "reporting_year",
                "visible": false
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "organization_name"
            }, {
                "mData": "status",
                "width": "50px"
            }, {
                "mData": "summary.lea",
                "width": "25px"
            }, {
                "mData": "summary.lea_mls",
                "width": "25px"
            }, {
                "mData": "summary.rua",
                "width": "25px"
            }, {
                "mData": "summary.rua_mls",
                "width": "25px"
            }, {
                "mData": "summary.ip_patent",
                "width": "70px"
            }, {
                "mData": "summary.ip_patent_mls",
                "width": "25px"
            }, {
                "mData": "summary.ip_pvp",
                "width": "55px"
            }, {
                "mData": "summary.ip_pvp_mls",
                "width": "25px"
            }, {
                "mData": "review_status",
                "width": "180px"
            }, {
                "mData": "ia_report_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    let review_btn = '<a href="/ia/iareporting/ia_report_id/' + data + '" target="_blank" class="btn default btn-xs purple-stripe">Go to report</a>';
                    let pdf_export_btn = '<a href="javascript:void(0);" data-href="/ia/iareportpdf" data-id="' + data + '" class="btn default btn-xs red-stripe export_pdf"><i class="fa fa-file-pdf-o"></i> Export as PDF</a>';
                    let excel_export_btn = '<a href="/ia/iareportagreementsexcel/ia_report_id/' + data + '" class="btn default btn-xs green-jungle-stripe export_excel"><i class="fa fa-file-excel-o"></i> Export Section 3 as Excel</a>';
                    if (!row.can_edit_ia_report && parseInt(row.is_draft) === 1)
                        review_btn = pdf_export_btn = excel_export_btn = '';

                    return review_btn + pdf_export_btn + excel_export_btn;
                },
                "width": "210px"
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": []
        });
    };

    const InitEvents = function () {
        $('select.select2').select2();
        $('select.select2partner').partnerSelect2();

        let window_onscroll = window.onscroll;
        let floating_filters_timeout = null;
        window.onscroll = function () {
            if (floating_filters_timeout)
                clearTimeout(floating_filters_timeout);
            floating_filters_timeout = setTimeout(function () {
                let element = $('.form-group.floating-filters');
                if (element.parent().offset().top + element.parent().height() - $(window).scrollTop() <= 0) {
                    element.next().css('margin-top', element.height())
                    element.addClass('filters-fixed-top')
                } else {
                    element.removeClass('filters-fixed-top')
                    element.next().css('margin-top', 0)
                }
            }, 25);

            if (window_onscroll != null && $.isFunction(window_onscroll))
                window_onscroll();
        };

        if (CanViewIaReportGeneralComments !== true) {
            $('.general_comments_textarea').each(function () {
                $(this).parents('.form-group:first').remove();
            });
            $('.reviewer_content_only').remove();
        }
        if (isIpPMU !== true) {
            $('.btn_save_general_comments').remove();
            $('.general_comments_textarea').prop('disabled', true);
        }

        $('body')
            .on('change', '.ia_review_dashboard_filters', function () {
                let reporting_year = $('.ia_review_dashboard_filters.ia_report_reporting_year').val();
                let last_reporting_year = $('.ia_review_dashboard_filters.ia_report_last_reporting_year').val();
                let partner_id = $('.ia_review_dashboard_filters.ia_report_partner_id').val();

                $.ajax({
                    url: GetIaReportSummaryDataLink,
                    type: 'post',
                    data: {
                        reporting_year: reporting_year,
                        partner_id: partner_id,
                    },
                    success: function (data) {
                        initMetricsChart(data.data, 'metrics_chart');
                    }
                });

                $('#review_portlet table').each(function () {
                    if (reporting_year != null && reporting_year !== '')
                        $(this).DataTable().column(1).search('^' + reporting_year.join('$|^') + '$', true, false).draw();
                    else
                        $(this).DataTable().column(1).search('').draw();
                    if (last_reporting_year != null && last_reporting_year !== '')
                        $(this).DataTable().column(2).search('^' + last_reporting_year.join('$|^') + '$', true, false).draw();
                    else
                        $(this).DataTable().column(2).search('').draw();
                    if (partner_id != null && partner_id !== '')
                        $(this).DataTable().column(3).search('^' + partner_id.join('$|^') + '$', true, false).draw();
                    else
                        $(this).DataTable().column(3).search('').draw();
                });

                GetGeneralComments(reporting_year);
            })
            .on('click', '.btn_save_general_comments', function (e) {
                let parent = $(this).parents('.form-group:first');
                let comment_element = parent.find('textarea');
                App.blockUI({
                    target: parent,
                    boxed: true
                });

                $.ajax({
                    url: SubmitIaReportGeneralCommentsLink,
                    type: 'post',
                    data: {
                        field_name: comment_element.attr('name'),
                        comment: comment_element.val()
                    },
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
                        App.alert({
                            type: 'success',  // alert's type
                            message: data.message,  // alert's message
                            closeInSeconds: 5

                        });
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
            })
            .on('change', '.show_responsibilities', function (e) {
                let show_responsibilities = $(this).prop('checked');
                $.ajax({
                    url: GetIaReportResponsibilitiesDataLink,
                    async: true,
                    success: function (data) {
                        let responsibilities = data;
                        $('#review_portlet table').each(function () {
                            let table = $(this);
                            try {
                                if (table.DataTable().ajax.url() != null) {
                                    let original_url = table.data('ajax_url');
                                    if (show_responsibilities)
                                        table.DataTable().ajax.url(original_url + responsibilities.ia_report_ids + responsibilities.ia_agreement_ids + responsibilities.ia_agreement_public_disclosure_ids).load();
                                    else
                                        table.DataTable().ajax.url(original_url).load();
                                }
                            } catch (e) {
                            }
                        });
                    }
                });
            })
            .on('change', '.show_all_years', function (e) {
                let year_select = $(this).parents('.filter_container').find('select.ia_review_dashboard_filters');
                if ($(this).prop('checked'))
                    year_select.find('option[value!=""]').prop('selected', true);
                else
                    year_select.val(year_select.data('default_year'));

                year_select.change();
            });
        $('.ia_report_reporting_year').change();
    };

    const initMetricsChart = function (data, container) {
        $.each(data, function (key, value) {
            let element = $('#' + container + ' #' + key + '_count');

            animateCounters(element, value);

            let additional_counts = element.parents('.desc:first').find('.additional_counts');
            if (data.hasOwnProperty(key + '_mls')) {
                let mls_element = additional_counts.find('#' + key + '_mls_count');
                if (mls_element.length === 0)
                    mls_element = additional_counts.append('<small>Related to MLS: <span id="' + key + '_mls_count">0</span></small>').find('#' + key + '_mls_count');
                animateCounters(mls_element, data[key + '_mls']);
            }
            if (data.hasOwnProperty(key + '_public_disclosure')) {
                let public_disclosure_element = element.parents('.desc:first').find('#' + key + '_public_disclosure_count');
                if (public_disclosure_element.length === 0)
                    public_disclosure_element = additional_counts.append('<small>Related public disclosures: <span id="' + key + '_public_disclosure_count">0</span></small>').find('#' + key + '_public_disclosure_count');
                animateCounters(public_disclosure_element, data[key + '_public_disclosure']);
            }
            if (data.hasOwnProperty(key + '_public_disclosure_missing')) {
                let public_disclosure_missing_element = element.parents('.desc:first').find('#' + key + '_public_disclosure_missing_count');
                if (public_disclosure_missing_element.length === 0)
                    public_disclosure_missing_element = additional_counts.append('<small>No public disclosure: <span id="' + key + '_public_disclosure_missing_count">0</span></small>').find('#' + key + '_public_disclosure_missing_count');
                animateCounters(public_disclosure_missing_element, data[key + '_public_disclosure_missing']);
            }
            if (data.hasOwnProperty(key + '_public_disclosure_issued')) {
                let public_disclosure_issued_element = element.parents('.desc:first').find('#' + key + '_public_disclosure_issued_count');
                if (public_disclosure_issued_element.length === 0)
                    public_disclosure_issued_element = additional_counts.append('<small>Issued public disclosures: <span id="' + key + '_public_disclosure_issued_count">0</span></small>').find('#' + key + '_public_disclosure_issued_count');
                animateCounters(public_disclosure_issued_element, data[key + '_public_disclosure_issued']);
            }
        });

        App.unblockUI($('#' + container).parent());
    };

    const animateCounters = function (element, value) {
        element.html(value)
            .prop('Counter', 0).animate({
            Counter: element.text()
        }, {
            duration: 1000,
            easing: 'swing',
            step: function (now) {
                $(this).text(Math.ceil(now));
            }
        });
    };

    const GetGeneralComments = function (years) {
        $('textarea.general_comments_textarea').val('');

        $.ajax({
            url: GetIaReportGeneralCommentsDataLink,
            type: 'post',
            data: {
                years: years
            },
            success: function (data) {
                $.each(data.data, function (index, value) {
                    $('textarea.general_comments_textarea[name="' + value.field_name + '"]').val(value.comment);
                });
            }
        });
    };

    return {
        init: function () {
            initIaReportTable();
            InitEvents();
        }
    };
}();
