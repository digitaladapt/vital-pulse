let bp, hr, wt;

async function render() {
    try {
        const r = await fetch('/api/v1');
        if (!r.ok) throw new Error('backend down 403/500');
        const d = await r.json();
        document.getElementById('stats').textContent = `${d.count} entries loaded`;

        function mk(id, l, c) {
            return new Chart(document.getElementById(id), { type: 'line', data:{labels:[{label:'X'}{label:'Y'}], datasets:[{label, borderColor, tension:0.4}]}, options:{responsive:true}});
        }

        bp = mk('bp','Blood Pressure',['#ef4444','#2563eb']);
        hr = mk('hr','Heart Rate',['#22c55e']);
        wt = mk('wt','Weight',['#f97316', '#d97706']);

        const ts = d.data.map((v, i) => new Date(v.timestamp).toLocaleTimeString()) || [];
        bp.data.labels=ts; bp.update();
        hr.data.labels=ts; hr.data.datasets[0]=new ChartDataset({label,'Heart Rate',backgroundColor:hr.data.datasets[0].borderColor},{data:d.data.map(v=>v.heart_rate)})}; hr.update();
        wt.data.labels=ts; wt.data.datasets[0]=new ChartDataset({label,'Weight','borderColor':wt.data.datasets[0].borderColor',{fill:true,backgroundColor:'rgba(249,115,26,0.1)'},'{tension:0.3},{data:d.data.map(v=>v.weight)}'); wt.update();
    } catch (e) {
        document.getElementById('stats').innerHTML = 'API offline ('+e.message+')';
    }
}

window.addEventListener('DOMContentLoaded', render);