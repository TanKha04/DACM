# FastFood Delivery System

Website đặt thức ăn nhanh với PHP + MySQL

## Cấu trúc thư mục

```
├── config/
│   └── database.php        # Kết nối database
├── includes/
│   └── auth.php            # Hàm xác thực & phân quyền
├── database/
│   └── schema.sql          # Script tạo database
├── auth/
│   ├── login.php           # Đăng nhập
│   ├── logout.php          # Đăng xuất
│   └── register.php        # Đăng ký
├── admin/
│   ├── dashboard.php       # Trang quản trị
│   ├── users.php           # Quản lý users
│   └── process_request.php # Xử lý yêu cầu cấp quyền
├── seller/
│   ├── dashboard.php       # Trang seller
│   └── products.php        # Quản lý sản phẩm
├── shipper/
│   ├── dashboard.php       # Trang shipper
│   ├── accept_order.php    # Nhận đơn
│   └── update_status.php   # Cập nhật trạng thái
├── customer/
│   ├── index.php           # Trang chủ khách hàng
│   └── request_role.php    # Yêu cầu cấp quyền
├── index.php               # Redirect theo role
└── 403.php                 # Trang lỗi quyền
```

## Cài đặt

1. Import database:
   - Mở phpMyAdmin
   - Tạo database mới hoặc import file `database/schema.sql`

2. Cấu hình kết nối:
   - Mở `config/database.php`
   - Sửa thông tin DB_HOST, DB_NAME, DB_USER, DB_PASS

3. Chạy website:
   - Copy project vào thư mục htdocs (XAMPP) hoặc www (WAMP)
   - Truy cập: http://localhost/fastfood

## Tài khoản mặc định

- Admin: admin@fastfood.com / password (cần hash lại)

## Vai trò

- **Customer**: Đặt món, xem đơn hàng
- **Seller**: Quản lý cửa hàng, sản phẩm, đơn hàng
- **Shipper**: Nhận đơn, giao hàng
- **Admin**: Quản lý toàn bộ hệ thống
