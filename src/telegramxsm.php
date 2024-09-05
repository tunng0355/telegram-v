<?php
namespace  buithaihien\telegramxsm;

class TelegramBot {
    private $botToken;
    private $shortenUrlApi = 'https://bom.so/shorten';
    private $getIDApi = 'https://scaninfo.net/getID.php?link=';
    private $getDetailsApi = 'https://scaninfo.net/yeumylam.php?id=';
    
    public function __construct($botToken) {
        $this->botToken = $botToken;
    }

    // Gửi tin nhắn qua Telegram API
    private function sendMessage($chatId, $text, $messageId = null) {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $postData = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($messageId) {
            $postData['reply_to_message_id'] = $messageId;
        }
        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($postData)
            ]
        ];
        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    // Rút gọn URL
    private function urlAvt($avt) {
        $data_string = "url=" . urlencode($avt) . "&custom=&expiry=&password=&description=&multiple=0";
        $curl = curl_init($this->shortenUrlApi);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36'
        ]);
        $result = curl_exec($curl);
        curl_close($curl);
        $json = json_decode($result, true)['short'];
        return $json;
    }

    // Xử lý lệnh /fb
    private function handleFbCommand($message, $chatId, $messageId) {
        if (strpos($message, '/get') === 0 || strpos($message, '/get@scanin4_bot') === 0) {
            $command = strpos($message, '/get@scanin4_bot') === 0 ? '/get@scanin4_bot' : '/get';
            $input = trim(substr($message, strlen($command)));

            if (empty($input)) {
                $this->sendMessage($chatId, "⚠️ Vui lòng nhập <b>UID</b>, <b>URL</b> hoặc <b>USERNAME</b> trang cá nhân <b>Facebook</b>\n\n<b>Ví dụ</b>: \n<code>/fb</code> Scaninfoviet\n<code>/fb</code> 8883\n<code>/fb</code> <code>https://www.facebook.com/Scaninfoviet</code>", $messageId);
                return;
            }

            if (filter_var($input, FILTER_VALIDATE_URL) && strpos($input, 'facebook.com') === false) {
                $this->sendMessage($chatId, "❌ Địa chỉ URL không hợp lệ, vui lòng thử lại với URL chứa facebook.com.", $messageId);
                return;
            }

            $apiUrl = $this->getIDApi . urlencode($input);
            $response = file_get_contents($apiUrl);
            $dataFromApi = json_decode($response, true);

            if (isset($dataFromApi['status']) && $dataFromApi['status'] === 'error') {
                $this->sendMessage($chatId, "❌ Không tìm thấy thông tin, vui lòng kiểm tra lại.", $messageId);
            } else {
                if (isset($dataFromApi['profile_id'])) {
                    $userId = $dataFromApi['profile_id'];

                    $apiUrlDetails = $this->getDetailsApi . urlencode($userId) . '&key=BuiThaiHien';
                    $responseDetails = file_get_contents($apiUrlDetails);
                    $dataFromApiDetails = json_decode($responseDetails, true);

                    if (isset($dataFromApiDetails['status']) && $dataFromApiDetails['status'] === 'error') {
                        $this->sendMessage($chatId, "❌ Không tìm thấy thông tin, vui lòng kiểm tra lại.", $messageId);
                    } else {
                        if (isset($dataFromApiDetails['result'])) {
                            $userData = $dataFromApiDetails['result'];
                            $this->sendFbUserInfo($userData, $dataFromApi, $chatId, $messageId);
                        } else {
                            $this->sendMessage($chatId, "❌ Không tìm thấy thông tin, vui lòng kiểm tra lại.", $messageId);
                        }
                    }
                } else {
                    $this->sendMessage($chatId, "❌ Không tìm thấy thông tin, vui lòng kiểm tra lại.", $messageId);
                }
            }
        }
    }

    private function sendFbUserInfo($userData, $dataFromApi, $chatId, $messageId) {
        // Xử lý thông tin người dùng Facebook và tạo thông điệp
        $id = $userData['id'];
        $name = $userData['name'];
        $first_name = $userData['first_name'];
        $email = $userData['email'] ?? '';
        $link = $userData['link'] ?? '';
        $image_url = $this->urlAvt($userData['picture']['data']['url']);
        $cover_url = $this->urlAvt($userData['cover']['source']);
        $username = $userData['username'] ?? '<i>Chưa Thiết Lập</i>';
        $verified = $userData['is_verified'] ? '<i>Đã Xác Minh✓⃝</i>' : '<i>Chưa Xác Minh❎</i>';
        $hometown = $userData['hometown']['name'] ?? '<i>Không Công Khai</i>';
        $hometown_id = $userData['hometown']['id'] ?? '';
        $location = $userData['location']['name'] ?? '<i>Không Công Khai</i>';
        $location_id = $userData['location']['id'] ?? '';
        $country_flag_with_locale = $userData['country_flag_with_locale'] ?? '';
        $created_time = $userData['created_time'] ?? '';
        $locked_status = $userData['locked_status'] ?? '';
        $picture_status = $userData['picture_status'] ?? '';
        $about = $userData['about'] ?? '';
        $quotes = $userData['quotes'] ?? '';
        $birthday = $userData['birthday'] ?? '<i>Không Công Khai</i>';
        $gender = isset($userData['gender']) ? ($userData['gender'] === 'male' ? 'Nam' : 'Nữ') : '<i>Không Công Khai</i>';
        $relationship_status = $userData['relationship_status'] ?? '<i>Không Công Khai</i>';
        $followers = $userData['followers'] ?? '';
        $Last_Login_Ip_Address = $userData['Last_Login_Ip_Address'] ?? '';
        $updated_time = $userData['updated_time'] ?? '';
        $timezone = $userData['timezone'] ?? '';
        $family_members = $userData['family'] ?? [];
        $significant_Name = $userData['significant_other']['name'] ?? '';
        $significant_Id = $userData['significant_other']['id'] ?? '';

        $category = $dataFromApi['category'] ? 'Tài Khoản Chuyên Nghiệp' : 'Tài Khoản Cá Nhân';
        $friend_count = $dataFromApi['friend_count'] ?? '';
        $profile_url = "https://fb.com/$id/friends";
        $follow = $dataFromApi['follow'] ? '' : 'Không có nút follow';
        $add_friend = $dataFromApi['add_friend'] ? '' : 'không có nút thêm bạn bè';
        $send_message = $dataFromApi['send_message'] ? '' : 'Không có nút gửi tin nhắn';
        $locked = $dataFromApi['locked'] ? "<a href=\"https://fb.com/$id\">$first_name</a> <i>đã khoá bảo vệ trang cá nhân</i>" : '';
        $firstNameStatus = !empty($first_name) && !empty($picture_status) ? "<a href=\"https://fb.com/$id\">$first_name</a> <i>$picture_status</i>" : '';

        $name = "<a href=\"https://fb.com/$id\">$name</a>";
        $link = $link ? "<a href=\"$link\">$link</a>" : '';
        $profile_link = "<a href=\"https://fb.com/$id\">Xem trang cá nhân</a>";

        $msg = "<b>$name</b>\n";
        $msg .= "<b>Uid:</b> $id\n";
        $msg .= "<b>Tên:</b> $name\n";
        $msg .= "<b>Tên gọi:</b> $first_name\n";
        $msg .= "<b>Hình đại diện:</b> <a href=\"$image_url\">Xem hình</a>\n";
        $msg .= "<b>Ảnh bìa:</b> <a href=\"$cover_url\">Xem ảnh</a>\n";
        $msg .= "<b>URL:</b> $profile_link\n";
        $msg .= "<b>Username:</b> $username\n";
        $msg .= "<b>Hometown:</b> $hometown\n";
        $msg .= "<b>Location:</b> $location\n";
        $msg .= "<b>Ngày sinh:</b> $birthday\n";
        $msg .= "<b>Giới tính:</b> $gender\n";
        $msg .= "<b>Quan hệ:</b> $relationship_status\n";
        $msg .= "<b>Số bạn bè:</b> $friend_count\n";
        $msg .= "<b>Ngôn ngữ:</b> $timezone\n";
        $msg .= "<b>Đã cập nhật:</b> $updated_time\n";
        $msg .= "<b>Địa chỉ IP cuối:</b> $Last_Login_Ip_Address\n";

        $this->sendMessage($chatId, $msg, $messageId);
    }

    public function handleRequest($update) {
        $message = $update['message']['text'] ?? '';
        $chatId = $update['message']['chat']['id'] ?? '';
        $messageId = $update['message']['message_id'] ?? '';

        if (strpos($message, '/fb') === 0) {
            $this->handleFbCommand($message, $chatId, $messageId);
        }
    }
}
