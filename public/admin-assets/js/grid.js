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
        var headers = columns.map(function (c) { return c.header; });
        var names = columns.map(function (c) { return c.name; });
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
    window.ndnGrid = function (cfg) {
        var Grid = tui.Grid;
        Grid.applyTheme('striped');

        var host = document.getElementById(cfg.el);
        var columns = cfg.columns.slice();

        function bodyHeight() {
            return Math.max(240, window.innerHeight - (cfg.offset || 200));
        }

        var grid = new Grid({
            el: host,
            data: cfg.data,
            columns: columns,
            rowHeaders: ['rowNum'],
            bodyHeight: bodyHeight(),
            minBodyHeight: 200,
            scrollX: true,
            scrollY: true,
            columnOptions: { resizable: true, frozenCount: cfg.frozenCount || 0 },
            pageOptions: cfg.perPage ? { useClient: true, perPage: cfg.perPage } : undefined,
        });

        window.addEventListener('resize', function () { grid.setBodyHeight(bodyHeight()); });

        // 툴바: 엑셀(CSV) 내보내기 + 열 초기화 + 필터 초기화
        buildToolbar(host, [
            { label: '엑셀(CSV) 내보내기', onClick: function () { exportCsv(grid, columns, cfg.title || 'ndn'); } },
            { label: '열 초기화', onClick: function () { grid.setColumns(cfg.columns.slice()); columns = cfg.columns.slice(); } },
            { label: '필터 초기화', onClick: function () { grid.unfilter(); } },
        ]);

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

        return grid;
    };
})();
