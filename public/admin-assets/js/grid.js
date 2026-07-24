/* ==========================================================================
   NDN 운영 콘솔 — Toast UI Grid(v4.21.5) 초기화 + 기능 확장
   fulfillment 과 동일한 tui-grid 엔진 위에, 그들의 레거시 jQuery 스택 없이
   toolbar / 열 재정렬 / 컬럼 필터 / CSV(엑셀) 내보내기 / 컨텍스트 메뉴 /
   셀 편집을 순정 tui-grid API + 경량 바닐라 JS 로 구현한다.
   ========================================================================== */
(function () {
    'use strict';

    /* 상태 pill 커스텀 렌더러: "라벨|kind" → mv2-pill */
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

    /* ---------------- CSV(엑셀) 내보내기 — 의존성 없음 ---------------- */
    function exportCsv(grid, columns, filename) {
        var cols = columns.filter(function (c) { return !c.hidden; });
        var headers = cols.map(function (c) { return c.header; });
        var names = cols.map(function (c) { return c.name; });
        var rows = grid.getData();
        var lines = [headers.map(csvCell).join(',')];
        rows.forEach(function (row) {
            lines.push(names.map(function (n) {
                var v = row[n];
                // "라벨|kind" pill 값은 라벨만
                if (typeof v === 'string' && v.indexOf('|') > -1) v = v.split('|')[0];
                return csvCell(v);
            }).join(','));
        });
        // UTF-8 BOM → Excel 한글 깨짐 방지
        var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (filename || 'export') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    function csvCell(v) {
        v = v == null ? '' : String(v);
        return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    }

    /* ---------------- 툴바 ---------------- */
    function buildToolbar(host, actions) {
        var bar = document.createElement('div');
        bar.className = 'ndn-gridbar';
        actions.forEach(function (a) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ndn-gridbar__btn';
            b.textContent = a.label;
            b.addEventListener('click', a.onClick);
            bar.appendChild(b);
        });
        host.parentNode.insertBefore(bar, host);
        return bar;
    }

    /* ---------------- 컨텍스트 메뉴 (우클릭) ---------------- */
    function attachContextMenu(grid, host, items) {
        var menu = document.createElement('ul');
        menu.className = 'ndn-ctxmenu';
        items.forEach(function (it) {
            var li = document.createElement('li');
            li.textContent = it.label;
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                hide();
                it.onClick();
            });
            menu.appendChild(li);
        });
        document.body.appendChild(menu);
        function hide() { menu.style.display = 'none'; }
        host.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
        });
        document.addEventListener('mousedown', function (e) {
            if (!menu.contains(e.target)) hide();
        });
        window.addEventListener('scroll', hide, true);
    }

    /* ---------------- 헤더 드래그로 열 재정렬 ---------------- */
    function enableColumnReorder(grid, host, columnsRef) {
        var dragName = null;
        host.addEventListener('mousedown', function (e) {
            var cell = e.target.closest('.tui-grid-cell-header');
            if (!cell) return;
            // 리사이즈 핸들 위에서는 재정렬 시작 안 함
            if (e.target.closest('.tui-grid-column-resize-handle')) return;
            dragName = cell.getAttribute('data-column-name');
        });
        host.addEventListener('mouseup', function (e) {
            if (!dragName) return;
            var cell = e.target.closest('.tui-grid-cell-header');
            var targetName = cell ? cell.getAttribute('data-column-name') : null;
            var from = dragName; dragName = null;
            if (!targetName || targetName === from) return;
            var cols = columnsRef.slice();
            var fi = cols.findIndex(function (c) { return c.name === from; });
            var ti = cols.findIndex(function (c) { return c.name === targetName; });
            if (fi < 0 || ti < 0) return;
            var moved = cols.splice(fi, 1)[0];
            cols.splice(ti, 0, moved);
            columnsRef.length = 0;
            Array.prototype.push.apply(columnsRef, cols);
            grid.setColumns(cols);
        });
    }

    /**
     * @param {Object} cfg { el, columns, data, perPage, frozenCount, offset,
     *                        title, editable, onEdit }
     */
    /* 세련된 라이트 테마 — 수직 보더 제거(모던), 옅은 헤더, 얇은 행 구분선 */
    function applyNdnTheme(Grid) {
        Grid.applyTheme('default', {
            grid: {
                background: '#FFFFFF',
                border: '#EAEDF1',
                text: '#2D3543',
            },
            selection: { background: 'rgba(30,156,146,.14)', border: '#1E9C92' },
            scrollbar: {
                border: '#EAEDF1', background: '#FFFFFF',
                emptySpace: '#FFFFFF', thumb: '#D5DBE3', active: '#B7BFCB',
            },
            cell: {
                normal: {
                    background: '#FFFFFF', border: '#EEF1F4',
                    text: '#2D3543',
                    showVerticalBorder: false, showHorizontalBorder: true,
                },
                header: {
                    background: '#F7F9FB', border: '#E2E5E9',
                    text: '#5C6878',
                    showVerticalBorder: false, showHorizontalBorder: true,
                },
                rowHeader: {
                    background: '#F7F9FB', border: '#EEF1F4', text: '#8A95A4',
                    showVerticalBorder: false, showHorizontalBorder: true,
                },
                selectedHeader: { background: '#EAEDF1' },
                focused: { border: '#1E9C92' },
                focusedInactive: { border: '#D5DBE3' },
                evenRow: { background: '#FFFFFF' },
                oddRow: { background: '#FCFDFE' },
                dummy: { background: '#FFFFFF' },
            },
        });
    }

    window.ndnGrid = function (cfg) {
        var Grid = tui.Grid;
        applyNdnTheme(Grid);

        var host = document.getElementById(cfg.el);
        var columns = cfg.columns.slice();

        // 그리드 본문 높이 = 뷰포트에서 (그리드 시작 위치 + 헤더 + 페이지네이션 +
        // 하단 여백)을 뺀 값. 화면 스크롤 없이 페이지네이션까지 보이도록 맞춘다.
        function bodyHeight() {
            var docTop = host.getBoundingClientRect().top + window.scrollY;
            var reserve = 44   // 그리드 헤더
                + 52           // 페이지네이션
                + 52;          // 하단 여백(.screen padding-bottom 등)
            return Math.max(200, Math.floor(window.innerHeight - docTop - reserve));
        }

        // 컬럼 폭 합이 컨테이너보다 좁으면 각 컬럼을 비율대로 늘려 표가 폭을 꽉
        // 채우게 한다 → 오른쪽에 빈 여백이나 떠 있는 스크롤바가 생기지 않는다.
        var ROWNUM_W = 66; // rowNum 헤더 폭
        function fitColumns() {
            var avail = host.getBoundingClientRect().width;
            if (!avail) return;
            var vis = columns.filter(function (c) { return !c.hidden; });
            var base = vis.map(function (c) { return c.width || c.minWidth || 100; });
            var dataSum = base.reduce(function (a, b) { return a + b; }, 0);
            var target = avail - ROWNUM_W - 2;   // 테두리 여유
            if (target <= dataSum) return;       // 넘치면 가로 스크롤(그대로 둠)
            var factor = target / dataSum;
            var used = 0;
            vis.forEach(function (c, i) {
                if (i === vis.length - 1) {
                    c.width = target - used;     // 반올림 잔여는 마지막 컬럼에
                } else {
                    c.width = Math.floor(base[i] * factor);
                    used += c.width;
                }
                delete c.minWidth;               // 고정폭으로 확정
            });
        }
        fitColumns();

        var grid = new Grid({
            el: host,
            data: cfg.data,
            columns: columns,
            rowHeaders: ['rowNum'],
            rowHeight: 42,
            minRowHeight: 42,
            header: { height: 44 },
            bodyHeight: bodyHeight(),
            minBodyHeight: 200,
            scrollX: true,
            scrollY: true,
            columnOptions: { resizable: true, frozenCount: cfg.frozenCount || 0 },
            pageOptions: cfg.perPage ? { useClient: true, perPage: cfg.perPage } : undefined,
        });

        window.addEventListener('resize', function () { grid.setBodyHeight(bodyHeight()); });

        // 툴바: 엑셀(CSV) 내보내기 + 열 초기화 + 필터 초기화
        var bar = buildToolbar(host, [
            { label: '엑셀(CSV) 내보내기', onClick: function () { exportCsv(grid, columns, cfg.title || 'ndn'); } },
            { label: '열 초기화', onClick: function () { grid.setColumns(cfg.columns.slice()); columns = cfg.columns.slice(); } },
            { label: '필터 초기화', onClick: function () { grid.unfilter(); search.value = ''; grid.resetData(fullData); } },
        ]);

        // 전역 검색: 모든 열을 대상으로 즉시 필터 (클라이언트 데이터 기준)
        var fullData = cfg.data.slice();
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'ndn-gridbar__search';
        search.placeholder = cfg.searchPlaceholder || '전체 검색';
        var searchTimer;
        search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                var q = search.value.trim().toLowerCase();
                if (!q) { grid.resetData(fullData); return; }
                grid.resetData(fullData.filter(function (row) {
                    return Object.keys(row).some(function (k) {
                        var v = row[k];
                        if (v == null) return false;
                        v = String(v);
                        if (v.indexOf('|') > -1) v = v.split('|')[0]; // pill "라벨|kind"
                        return v.toLowerCase().indexOf(q) > -1;
                    });
                }));
            }, 160);
        });
        bar.insertBefore(search, bar.firstChild);

        // 툴바·검색창이 삽입되며 그리드가 아래로 밀렸으므로 본문 높이를 다시 맞춘다
        grid.setBodyHeight(bodyHeight());

        // 컨텍스트 메뉴
        attachContextMenu(grid, host, [
            { label: '엑셀(CSV) 내보내기', onClick: function () { exportCsv(grid, columns, cfg.title || 'ndn'); } },
            { label: '선택 영역 복사', onClick: function () { grid.copyToClipboard && grid.copyToClipboard(); } },
        ]);

        // 열 재정렬
        enableColumnReorder(grid, host, columns);

        // 셀 편집 저장 훅
        if (typeof cfg.onEdit === 'function') {
            grid.on('afterChange', function (ev) {
                (ev.changes || []).forEach(function (ch) {
                    var row = grid.getRow(ch.rowKey);
                    cfg.onEdit(row, ch.columnName, ch.value, ch.prevValue, grid);
                });
            });
        }

        // 행 더블클릭 훅 (상세 팝업 등). 편집 가능한 셀에서는 에디터에 양보한다.
        if (typeof cfg.onRowDblClick === 'function') {
            grid.on('dblclick', function (ev) {
                if (ev.rowKey == null) return;
                var col = null;
                for (var i = 0; i < columns.length; i++) {
                    if (columns[i].name === ev.columnName) { col = columns[i]; break; }
                }
                if (col && col.editor) return;   // 편집 셀 더블클릭은 편집으로
                var row = grid.getRow(ev.rowKey);
                if (row) cfg.onRowDblClick(row, grid, ev);
            });
        }

        return grid;
    };
})();
