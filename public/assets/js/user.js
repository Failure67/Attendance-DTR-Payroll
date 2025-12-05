// © 2025 Romar Jabez

$(document).ready(function() {
   
    $('.content.selector .selector-item').on('click', function() {
        $('.content.selector .selector-item').removeClass('selected');

        $(this).addClass('selected');

        const index = $(this).index();

        $('.container.employee.payroll-history').hide();
        $('.container.employee.attendance').hide();
        $('.container.employee:not(.header):not(.payroll-history):not(.attendance)').hide();

        if (index === 0) {
            $('.container.employee:not(.header):not(.payroll-history):not(.attendance)').show();
        } else if (index === 1) {
            $('.container.employee.payroll-history').show();
        } else if (index === 2) {
            $('.container.employee.attendance').show();
        }
    });

});