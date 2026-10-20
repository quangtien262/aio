import ReadOutlined from '@ant-design/icons/ReadOutlined';
import SearchOutlined from '@ant-design/icons/SearchOutlined';
import Alert from 'antd/es/alert';
import Collapse from 'antd/es/collapse';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import { useState } from 'react';
import FnbPageHeader from '../../components/FnbPageHeader';

// Nội dung đi cùng phiên bản phần mềm, không lấy từ dữ liệu điểm bán.
const topics = [
    { key: 'start', title: 'Bắt đầu sử dụng', description: 'Chuẩn bị quán và nắm quy trình phục vụ.', articles: [
        { title: 'Chuẩn bị trước khi bán hàng', steps: [
            'Chọn đúng Điểm bán và Quầy bán hàng ở thanh phía trên. Bán hàng cần thiết bị loại POS.',
            'Trong Cấu hình → Thiết lập, kiểm tra khu vực, bàn, trạm Bar / Bếp và phương thức thanh toán.',
            'Tạo món, size và giá trong Thực đơn; phát hành thực đơn cho điểm bán để đưa vào sử dụng.',
            'Vào Ca bán hàng, mở ngày kinh doanh và mở ca cho quầy với số tiền đầu ca thực tế.',
        ] },
        { title: 'Một lượt phục vụ từ đầu đến cuối', steps: [
            'Bán hàng → chọn bàn trống hoặc Mở phiên mua nhanh, nhập thông tin mở phiên.',
            'Thêm món, chọn size và topping nếu có, kiểm tra số lượng rồi Gửi Bar / Bếp.',
            'Bar / Bếp tiếp nhận và cập nhật tiến độ; nhân viên xác nhận Đã giao món khi khách nhận món.',
            'Thanh toán / tách bill → kiểm tra bill → thu tiền bằng các hình thức khách chọn.',
            'Hoàn tất phục vụ và thanh toán, sau đó xác nhận trả bàn để bàn sẵn sàng đón khách mới.',
        ], note: 'Thanh toán và hoàn tất phục vụ là hai việc riêng. Đã thu đủ tiền chưa có nghĩa là tất cả món đã được giao.' },
    ] },
    { key: 'sales', title: 'Bán hàng & phục vụ', description: 'Gọi món, quản lý bàn và theo dõi phiên phục vụ.', articles: [
        { title: 'Mở bàn, mua nhanh và gọi thêm món', steps: [
            'Trong Bán hàng, chọn bàn trống để mở phiên. Với khách không ngồi bàn, chọn Mở phiên mua nhanh.',
            'Trong chi tiết phiên, chọn Thêm món; tìm theo tên hoặc nhóm, chọn món và kiểm tra size, topping, ghi chú, số lượng.',
            'Món mới nằm trong order nháp. Có thể sửa hoặc xóa dòng nháp trước khi gửi chế biến.',
            'Chọn Gửi Bar / Bếp khi đã kiểm tra xong. Các lần gọi thêm tiếp theo được theo dõi trong cùng phiên.',
        ] },
        { title: 'Chuyển bàn và hoàn tất phục vụ', steps: [
            'Mở phiên đang phục vụ, chọn Chuyển bàn, chọn bàn đích khả dụng và xác nhận.',
            'Theo dõi trạng thái từng món trong chi tiết phiên hoặc màn hình Hoàn tất phục vụ.',
            'Cập nhật Bắt đầu pha → Đã làm xong → Đã giao món theo tiến độ thực tế.',
            'Nút Hoàn thành trên đơn giúp hoàn tất nhanh các món của đơn. Chỉ dùng khi đã kiểm tra toàn bộ món.',
            'Chọn Trả bàn và làm theo các điều kiện hiển thị; chỉ xác nhận khi phục vụ và thanh toán đã hoàn tất.',
        ] },
        { title: 'Giảm giá, hủy món và hoàn tiền', steps: [
            'Với order nháp, dùng Giảm giá order nếu tài khoản được cấp quyền, rồi kiểm tra tổng tiền sau giảm.',
            'Món nháp có thể xóa trực tiếp. Với món đã gửi hoặc đã thanh toán, dùng thao tác hủy / hoàn tiền được cung cấp theo trạng thái.',
            'Kiểm tra đúng món, số lượng, bill và lý do trước khi xác nhận; hoàn tất xác thực hoặc phê duyệt nếu hệ thống yêu cầu.',
        ], note: 'Nếu một thao tác chưa hiện, kiểm tra trạng thái đơn và quyền tài khoản với quản lý.' },
    ] },
    { key: 'kitchen', title: 'Bar / Bếp', description: 'Nhận món, cập nhật chế biến và xem món đã hoàn thành.', articles: [
        { title: 'Tiếp nhận và cập nhật tiến độ món', steps: [
            'Mở Bar / Bếp; chọn trạm chế biến cần theo dõi hoặc Tất cả trạm.',
            'Dùng các tab Đang làm, Chờ pha chế, Đang pha và Chờ phục vụ để lọc công việc.',
            'Chọn Bắt đầu pha khi nhận món; Báo xong khi sẵn sàng phục vụ; Đã giao món khi đã giao cho khách.',
            'Theo dõi thanh tiến độ dưới từng món. Tiêu đề thẻ ưu tiên tên bàn nếu đơn có bàn.',
        ] },
        { title: 'Hoàn thành nhanh và tra cứu món cũ', steps: [
            'Chọn Hoàn thành ở đầu thẻ đơn để hoàn tất nhanh các món của đơn; kiểm tra kỹ trước khi dùng.',
            'Chọn tab Hoàn thành để xem lại các món đã hoàn thành.',
            'Dùng Đồng bộ khi cần tải lại; kiểm tra trạng thái kết nối và thời điểm cập nhật trên màn hình.',
            'Có thể bật Toàn màn hình để theo dõi thuận tiện tại quầy pha chế hoặc bếp.',
        ] },
    ] },
    { key: 'payment', title: 'Thanh toán & in phiếu', description: 'Tách bill, kết hợp hình thức thanh toán và dùng QR.', articles: [
        { title: 'Tạo bill và thu tiền', steps: [
            'Mở chi tiết phiên, chọn Thanh toán / tách bill. Chọn các dòng món cần đưa vào bill và Chốt các dòng đã chọn.',
            'Kiểm tra số tiền cần thanh toán. Chọn một hoặc nhiều hình thức đang bật: tiền mặt, chuyển khoản hoặc thẻ.',
            'Nếu kết hợp nhiều hình thức, nhập số tiền cho từng phần. Hệ thống tự cân đối phần còn lại; kiểm tra tổng phân bổ bằng số phải thu.',
            'Với tiền mặt, Khách đưa được điền theo phần tiền mặt. Sửa lại nếu khách đưa nhiều hơn để tính tiền thừa.',
            'Kiểm tra từng phần đã nhận tiền, rồi Xác nhận thu tiền. Có thể thu riêng từng bill bằng Thu bill này.',
        ] },
        { title: 'Nhận chuyển khoản bằng QR', steps: [
            'Quản lý vào Cấu hình → Thiết lập → Ngân hàng & in phiếu, thêm ngân hàng, số tài khoản và tên chủ tài khoản.',
            'Bật nhận chuyển khoản và chọn tài khoản mặc định. Kiểm tra chính xác thông tin người nhận trước khi lưu.',
            'Khi thu tiền, chọn Chuyển khoản; QR hiển thị theo tài khoản nhận và số tiền của phần chuyển khoản.',
            'Cho khách quét QR; kiểm tra số tiền, người nhận, nội dung và thời hạn hiển thị. Nếu đổi số tiền, dùng QR mới đang hiển thị.',
            'Kiểm tra tiền thực tế đã vào tài khoản trước khi xác nhận thu. Dùng nhập mã giao dịch khi cần và khi màn hình cho phép.',
        ], note: 'QR giúp điền thông tin chuyển khoản. Việc hiển thị QR không phải xác nhận đã nhận được tiền.' },
        { title: 'In lại phiếu và xử lý giao dịch chưa rõ kết quả', steps: [
            'Trong Bill & thanh toán, chọn In phiếu bán hàng trên bill đã chốt và chọn máy in phù hợp.',
            'Kiểm tra giấy, máy in và thiết lập in của trình duyệt nếu phiếu không ra; có thể in lại từ bill.',
            'Nếu giao dịch đang xử lý hoặc mất kết nối khi xác nhận, đồng bộ và kiểm tra kết quả trước khi thu lại để tránh ghi nhận trùng.',
        ] },
    ] },
    { key: 'menu', title: 'Thực đơn', description: 'Quản lý món, giá, hình ảnh và phát hành thay đổi.', articles: [
        { title: 'Thêm món, size, topping và ảnh đại diện', steps: [
            'Vào Thực đơn, chuẩn bị Nhóm món, nhóm topping và nguyên liệu nếu sử dụng.',
            'Chọn Thêm món, nhập mã, tên, nhóm món và loại món phù hợp.',
            'Khai báo các size, giá bán, trạm chế biến và size mặc định; kiểm tra trạng thái đang bán.',
            'Tải một hoặc nhiều ảnh trong Hình ảnh món, chọn ảnh đại diện. Ảnh đại diện được dùng ở danh sách món.',
            'Mở Thiết lập thêm để bổ sung các thông tin cần thiết; lưu món và kiểm tra lại trước khi phát hành.',
        ] },
        { title: 'Phát hành thực đơn và tạm hết món', steps: [
            'Các thay đổi thực đơn được soạn trong bản nháp. Chọn Phát hành cho điểm bán sau khi đã kiểm tra món, size và giá.',
            'Chọn đúng điểm bán cần áp dụng. Order đã chốt giữ nội dung và giá tại thời điểm ghi nhận.',
            'Dùng thao tác Hết món / trạng thái bán phù hợp khi cần ngừng nhận món; cập nhật lại khi có thể bán tiếp.',
        ] },
    ] },
    { key: 'customers', title: 'Khách hàng', description: 'Tạo hồ sơ, tra cứu lịch sử và khôi phục khách đã xóa.', articles: [
        { title: 'Thêm, sửa và tra cứu khách hàng', steps: [
            'Mở Khách hàng trên thanh điều hướng; tìm theo tên, mã, số điện thoại hoặc email.',
            'Chọn Thêm khách hàng, nhập tên và các thông tin liên hệ cần lưu; chỉ ghi nhận đồng ý của khách khi khách đã đồng ý.',
            'Chọn tên khách hoặc Xem để mở hồ sơ và lịch sử mua hàng; dùng Sửa để cập nhật thông tin.',
            'Trong POS, tra cứu và chọn hồ sơ khách cho phiên khi chức năng và quyền của tài khoản cho phép.',
        ] },
        { title: 'Xóa và khôi phục khách hàng', steps: [
            'Chọn Xóa ở đúng hồ sơ và xác nhận. Hồ sơ được chuyển sang mục Đã xóa và không còn xuất hiện trong tra cứu khách đang hoạt động tại POS.',
            'Chuyển bộ lọc sang Đã xóa để tìm hồ sơ và chọn Khôi phục khi cần dùng lại.',
            'Lịch sử giao dịch và thông tin người mua đã ghi nhận trên bill cũ vẫn được giữ nguyên.',
        ] },
    ] },
    { key: 'shifts', title: 'Ca bán hàng & báo cáo', description: 'Mở ca, kiểm tiền cuối ca và theo dõi hoạt động.', articles: [
        { title: 'Mở ca, thu chi két và kết ca', steps: [
            'Chọn đúng quầy, vào Ca bán hàng. Mở ngày kinh doanh nếu chưa mở, sau đó mở ca với số tiền đầu ca thực tế.',
            'Ghi nhận thu / chi tiền két bằng thao tác tương ứng, kèm lý do để tiện kiểm tra.',
            'Cuối ca, kiểm đếm tiền thực tế rồi thực hiện đóng ca theo các trường hệ thống yêu cầu.',
            'Kiểm tra chênh lệch và đối soát trong lịch sử ca khi được cấp quyền. Chỉ đóng ngày sau khi mọi ca và phiên phục vụ đã đóng.',
        ] },
        { title: 'Xem tổng quan và báo cáo', steps: [
            'Vào Tổng quan → Hôm nay để xem tiền đã thu, thực thu sau hoàn, phiên phục vụ và ca đang mở.',
            'Chuyển sang Báo cáo, chọn khoảng thời gian và bộ lọc có sẵn để kiểm tra hoạt động.',
            'Đối chiếu đúng điểm bán và khoảng thời gian. Nội dung báo cáo hiển thị theo quyền của tài khoản.',
        ] },
    ] },
    { key: 'settings', title: 'Cấu hình & phân quyền', description: 'Thiết lập vận hành và quản lý quyền nhân viên.', articles: [
        { title: 'Thiết lập quán và tài khoản nhân viên', steps: [
            'Mở dropdown Cấu hình → Thiết lập để quản lý thông tin điểm bán, thiết bị POS / KDS, khu vực, bàn và trạm chế biến.',
            'Thiết lập hình thức thanh toán và tài khoản ngân hàng mặc định trước khi thu tiền.',
            'Vào Cấu hình → Nhân viên để quản lý quyền theo công việc và phạm vi điểm bán được giao.',
            'Với thao tác nhạy cảm, thực hiện xác thực lại và phê duyệt theo yêu cầu trên màn hình.',
        ], note: 'Chỉ những mục được cấp quyền mới hiển thị. Hướng dẫn có thể đề cập chức năng ngoài quyền của tài khoản hiện tại.' },
        { title: 'Điều hành nhiều điểm bán và tích hợp', steps: [
            'Dùng Điều hành chuỗi khi được cấp quyền quản lý nhiều điểm bán; kiểm tra phạm vi áp dụng trước mỗi thay đổi.',
            'Vào Tích hợp & đồng bộ để xem và quản lý các tích hợp được hỗ trợ trên hệ thống.',
            'Sau khi đổi cấu hình, tải lại dữ liệu tại màn hình vận hành nếu cần và kiểm tra điểm bán / quầy đang chọn.',
        ] },
    ] },
    { key: 'troubleshooting', title: 'Câu hỏi thường gặp', description: 'Xử lý các tình huống thường gặp trong ca làm việc.', articles: [
        { title: 'Không thấy món hoặc không gửi được Bar / Bếp', steps: [
            'Kiểm tra đúng điểm bán, món còn đang bán và thực đơn đã được phát hành cho điểm bán đó.',
            'Kiểm tra size của món có trạm chế biến phù hợp và order có dòng món.',
            'Kiểm tra ca đã mở, quầy là POS, trạng thái vận hành và quyền gửi món của tài khoản. Đọc thông báo lỗi trên màn hình trước khi thử lại.',
        ] },
        { title: 'Không trả được bàn hoặc dữ liệu đã thay đổi', steps: [
            'Nếu chưa trả được bàn, mở Hoàn tất phục vụ để kiểm tra các món chưa giao và kiểm tra bill còn phải thu.',
            'Nếu báo dữ liệu đã được người khác cập nhật, tải lại bản mới rồi kiểm tra lại trước khi thực hiện thao tác.',
            'Nếu mất kết nối hoặc chưa rõ kết quả thanh toán, kiểm tra lịch sử bill và đồng bộ trước khi thao tác lại. Liên hệ quản lý nếu vẫn không xác định được kết quả.',
        ] },
    ] },
];

const searchable = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();

export default function FnbGuidePage() {
    const [selected, setSelected] = useState('start');
    const [query, setQuery] = useState('');
    const words = searchable(query.trim()).split(/\s+/).filter(Boolean);
    const searching = words.length > 0;
    const results = topics.map((topic) => ({ ...topic, articles: topic.articles.filter((article) => {
        const text = searchable([topic.title, article.title, ...article.steps, article.note ?? ''].join(' '));
        return words.every((word) => text.includes(word));
    }) })).filter((topic) => topic.articles.length && (searching || topic.key === selected));
    const count = results.reduce((total, topic) => total + topic.articles.length, 0);

    return <div className="fnb-page-stack fnb-guide-page">
        <FnbPageHeader eyebrow="TÀI LIỆU VẬN HÀNH" title="Hướng dẫn sử dụng" description="Các bước sử dụng phần mềm quản lý nhà hàng, từ mở ca đến hoàn tất phục vụ." />
        <div className="fnb-guide-layout">
            <aside className="fnb-guide-sidebar">
                <div className="fnb-guide-sidebar-title"><ReadOutlined /> Nội dung hướng dẫn</div>
                <nav aria-label="Chủ đề hướng dẫn">{topics.map((topic, index) => <button key={topic.key} type="button" className={`fnb-guide-topic${!searching && selected === topic.key ? ' is-active' : ''}`} aria-current={!searching && selected === topic.key ? 'page' : undefined} onClick={() => { setSelected(topic.key); setQuery(''); }}><span className="fnb-guide-topic-number">{String(index + 1).padStart(2, '0')}</span>{topic.title}</button>)}</nav>
            </aside>
            <section className="fnb-guide-content" aria-label="Nội dung hướng dẫn">
                <Input size="large" prefix={<SearchOutlined />} allowClear value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm hướng dẫn: mở ca, chuyển khoản, thêm món…" aria-label="Tìm hướng dẫn" />
                {searching ? <p className="fnb-guide-search-count" role="status">{count} hướng dẫn phù hợp</p> : null}
                {results.length ? results.map((topic) => <section key={topic.key} className="fnb-guide-section">
                    <div className="fnb-guide-section-heading"><h2>{topic.title}</h2><p>{topic.description}</p></div>
                    <Collapse key={`${topic.key}:${searching ? query : 'browse'}`} defaultActiveKey={searching ? topic.articles.map((article) => article.title) : [topic.articles[0].title]} items={topic.articles.map((article) => ({ key: article.title, label: article.title, children: <><ol className="fnb-guide-steps">{article.steps.map((step) => <li key={step}>{step}</li>)}</ol>{article.note ? <Alert type="info" showIcon message={article.note} /> : null}</> }))} />
                </section>) : <Empty description="Không tìm thấy hướng dẫn. Thử từ khóa ngắn hơn hoặc chọn một chủ đề." />}
            </section>
        </div>
    </div>;
}
