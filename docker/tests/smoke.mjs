// Destructive-environment guard: only an isolated, empty test installation is accepted.
// Usage: COMPOSE_PROJECT_NAME=hnieoj-unified-test WEB_PORT=18888 node docker/tests/smoke.mjs
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {setTimeout as delay} from 'node:timers/promises';

const project = process.env.COMPOSE_PROJECT_NAME;
assert.match(project || '', /^hnieoj-[a-z0-9-]+-test$/, 'Use an explicitly named isolated test project');
const base = `http://127.0.0.1:${process.env.WEB_PORT || 18888}`;
const compose = (...args) => execFileSync('docker', ['compose', '-p', project, ...args],
    {encoding: 'utf8', maxBuffer: 4 * 1024 * 1024}).trim();
const config = JSON.parse(compose('config', '--format', 'json'));
assert.ok(config.services.web.volumes.every(v => v.type === 'volume'), 'Web must not mount host source');
assert.ok(config.services.judge.volumes.filter(v => v.type === 'bind')
    .every(v => v.source === '/var/run/docker.sock'), 'No host judge binaries or data directories');
assert.ok(!config.services.judge.privileged, 'Scheduler must not be privileged');
assert.notEqual(config.services.judge.network_mode, 'host');
const sql = query => execFileSync('docker', ['compose', '-p', project, 'exec', '-T', 'db',
    'sh', '-c', 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot -N -B jol'],
    {input: query, encoding: 'utf8'}).trim();
assert.equal(sql('SELECT COUNT(*) FROM users'), '1', 'Refusing a non-empty user database');
assert.equal(sql("SELECT COUNT(*) FROM problem WHERE title <> 'Compose smoke A+B'"), '0', 'Refusing real problem data');
const password = compose('exec', '-T', 'db', 'cat', '/run/oj-secrets/admin-password');
const cookies = new Map();
async function request(path, data) {
    const response = await fetch(base + path, {
        redirect: 'manual', signal: AbortSignal.timeout(15000),
        // Recreate deliberately closes the server; don't reuse pre-recreate sockets.
        headers: {Connection: 'close', Cookie: [...cookies].map(([k,v]) => `${k}=${v}`).join('; ')},
        ...(data ? {method: 'POST', body: new URLSearchParams(data)} : {}),
    });
    for (const cookie of response.headers.getSetCookie()) {
        const [pair] = cookie.split(';');
        const at = pair.indexOf('=');
        cookies.set(pair.slice(0, at), pair.slice(at + 1));
    }
    assert.ok(response.status < 400, `${path}: HTTP ${response.status}`);
    const body = await response.text();
    assert.doesNotMatch(body, /Fatal error|Uncaught (?:Error|TypeError|PDOException)/, path);
    return body;
}
await request('/loginpage.php');
const login = await request('/login.php', {user_id: 'admin', password});
assert.doesNotMatch(login, /用户名或密码错误|Verify Code Wrong/);
const page = await request('/admin/problem_add_page.php');
assert.doesNotMatch(page, /Please Login First/);
const postkey = page.match(/name=["']?postkey["']?\s+value=["']([^"']+)/)?.[1];
assert.ok(postkey, 'The normal add-problem form must contain its required postkey');
if (sql('SELECT COUNT(*) FROM problem') === '0') {
    await request('/admin/problem_add.php', {title: 'must-not-be-created'});
    assert.equal(sql('SELECT COUNT(*) FROM problem'), '0', 'Missing postkey is rejected');
    await request('/admin/problem_add.php', {postkey: 'invalid', title: 'must-not-be-created'});
    assert.equal(sql('SELECT COUNT(*) FROM problem'), '0', 'Invalid postkey is rejected');
    await request('/admin/problem_add.php', {
        postkey, title: 'Compose smoke A+B', time_limit: '1', memory_limit: '128',
        description: 'Read two integers and print their sum.', input: 'a b', output: 'sum',
        sample_input: '1 2\n', sample_output: '3\n', test_input: '123 456\n', test_output: '579\n',
        hint: '', source: 'Compose verification', spj: '0',
    });
}
assert.equal(sql('SELECT COUNT(*) FROM problem'), '1', 'Backend add-problem persisted one problem');
const pid = sql('SELECT problem_id FROM problem');
assert.match(pid, /^\d+$/);
sql(`UPDATE problem SET defunct='N' WHERE problem_id=${pid}`);
for (const path of ['/', '/problemset.php', `/problem.php?id=${pid}`, '/knowledge_graph.php', '/status.php']) {
    await request(path);
}
console.log('PASS: fresh admin login, backend problem creation, public pages');

const tests = [
    ['C++ AC', 1, '#include <iostream>\nint main(){long a,b;std::cin>>a>>b;std::cout<<a+b<<"\\n";}\n', 4],
    ['C++ WA', 1, '#include <iostream>\nint main(){std::cout<<0<<"\\n";}\n', 6],
    ['C++ CE', 1, 'this is intentionally not C++ code\n', 11],
    ['Python AC', 6, 'a,b=map(int,input().split())\nprint(a+b)\n', 4],
    ['Java AC', 3, 'import java.util.*; public class Main { public static void main(String[] x) { Scanner s=new Scanner(System.in); System.out.println(s.nextInt()+s.nextInt()); }}\n', 4],
    ['C AC', 0, '#include <stdio.h>\nint main(){int a,b;scanf("%d%d",&a,&b);printf("%d\\n",a+b);return 0;}\n', 4],
    ['Pascal AC', 2, 'var a,b:longint; begin readln(a,b); writeln(a+b); end.\n', 4],
    ['Clang AC', 13, '#include <stdio.h>\nint main(){int a,b;scanf("%d%d",&a,&b);printf("%d\\n",a+b);return 0;}\n', 4],
    ['Clang++ AC', 14, '#include <iostream>\nint main(){int a,b;std::cin>>a>>b;std::cout<<a+b<<"\\n";}\n', 4],
];
for (const [name, language, source, expected] of tests) {
    // Respect the real site's ten-second submission interval, including reruns.
    await delay(11000);
    const previous = Number(sql('SELECT COALESCE(MAX(solution_id),0) FROM solution'));
    await request('/submit.php', {id: pid, language: String(language), source});
    const sid = Number(sql('SELECT COALESCE(MAX(solution_id),0) FROM solution'));
    assert.ok(sid > previous, `${name}: normal HTTP submission created a solution`);
    let result;
    for (let i=0; i<90; i++) {
        result = Number(sql(`SELECT result FROM solution WHERE solution_id=${sid}`));
        if (result >= 4 && result <= 13) break;
        await delay(1000);
    }
    assert.equal(result, expected, `${name}: solution ${sid}; inspect compileinfo/runtimeinfo on failure`);
    console.log(`PASS: ${name}, solution=${sid}, result=${result}`);
}
compose('exec', '-T', 'web', 'php', '/home/judge/src/web/tests/knowledge_graph_test.php');
console.log('PASS: knowledge graph unit regression');
const before = sql('SELECT COUNT(*),SUM(result),SUM(code_length) FROM solution');
const inputBefore = compose('exec', '-T', 'web', 'sha256sum', `/home/judge/data/${pid}/test.in`);
compose('exec', '-T', 'web', 'php', '-r', 'file_put_contents("/home/judge/src/web/upload/compose-smoke.txt", "persisted-upload");');
compose('up', '-d', '--force-recreate', '--wait', '--wait-timeout', '180');
assert.equal(sql('SELECT COUNT(*),SUM(result),SUM(code_length) FROM solution'), before);
assert.ok(compose('exec', '-T', 'db', 'cat', '/run/oj-secrets/admin-password') === password,
    'Initial password must persist (values intentionally omitted)');
assert.equal(compose('exec', '-T', 'web', 'sha256sum', `/home/judge/data/${pid}/test.in`), inputBefore);
assert.equal(await request('/upload/compose-smoke.txt'), 'persisted-upload');
cookies.clear();
await request('/loginpage.php');
await request('/login.php', {user_id: 'admin', password});
assert.doesNotMatch(await request('/admin/problem_add_page.php'), /Please Login First/);
console.log('PASS: force-recreate preserves database, credentials, test data, upload and login');
