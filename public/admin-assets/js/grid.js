/* ==========================================================================
   NDN 운영 콘솔 — Toast UI Grid 초기화 헬퍼
   fulfillment 과 동일한 tui-grid(v4.21)를 자체호스팅으로 사용한다.
   컬럼 리사이즈·정렬·컬럼 고정·클라이언트 페이징을 제공.
   ========================================================================== */
(function () {
    'use strict';

    // 상태 pill 커스텀 렌더러: 셀 값 "라벨|kind" 를 mv2-pill 로 렌더
    function PillRenderer(props) {
        this.el = document.createElement('span');
        this.render(props);
    }
    PillRenderer.prototype.getElement = function () { return this.el; };
    PillRenderer.prototype.render = function (props) {
        var parts = String(props.value == null ? '' : props.value).split('|');
        this.el.className = 'mv2-pill' + (parts[1] ? ' mv2-pill--' + parts[1] : '');
        this.el.textContent = parts[0] || '';
    };
    window.NDN_PillRenderer = PillRenderer;

    /**
     * @param {Object} cfg { el, columns, data, perPage, frozenCount, offset }
     */
    window.ndnGrid = function (cfg) {
        var Grid = tui.Grid;
        Grid.applyTheme('striped');

        var host = document.getElementById(cfg.el);

        function bodyHeight() {
            // iframe 뷰포트 높이에서 화면 머리말 높이를 뺀 값
            return Math.max(240, window.innerHeight - (cfg.offset || 160));
        }

        var grid = new Grid({
            el: host,
            data: cfg.data,
            columns: cfg.columns,
            rowHeaders: ['rowNum'],
            bodyHeight: bodyHeight(),
            minBodyHeight: 200,
            scrollX: true,
            scrollY: true,
            columnOptions: {
                resizable: true,
                frozenCount: cfg.frozenCount || 0,
            },
            pageOptions: cfg.perPage ? { useClient: true, perPage: cfg.perPage } : undefined,
        });

        window.addEventListener('resize', function () {
            grid.setBodyHeight(bodyHeight());
        });

        return grid;
    };
})();
