<?php

/**
 * Live support — phiên chat với khách (live_support_sessions.status).
 *
 * REST bridge máy chủ tin cậy: {@see \App\Http\Controllers\LiveSupportBridgeController}
 */
return [

    /*
    | Danh sách trạng thái hợp lệ (comma-separated). VD: open,closed,done
    */
    'allowed_session_statuses' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('LIVE_SUPPORT_SESSION_STATUSES', 'open,closed,done'))
    ))),

    /*
    | Trạng thái mặc định khi tạo session (phải nằm trong allowed_session_statuses).
    */
    'default_session_status' => env('LIVE_SUPPORT_DEFAULT_SESSION_STATUS', 'open'),

    /*
    | Chỉ những trạng thái này cho phép gửi tin mới qua bridge (comma-separated).
    */
    'statuses_allowing_new_messages' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('LIVE_SUPPORT_STATUSES_ALLOWING_MESSAGES', 'open'))
    ))),

];
