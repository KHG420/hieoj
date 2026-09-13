-- HnieOJ knowledge graph schema and initial curriculum.
-- Idempotent: safe to apply more than once.

CREATE TABLE IF NOT EXISTS `knowledge_node` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `domain` varchar(64) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_knowledge_node_slug` (`slug`),
  KEY `idx_knowledge_node_domain_status_sort` (`domain`,`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `knowledge_edge` (
  `source_node_id` int(11) NOT NULL,
  `target_node_id` int(11) NOT NULL,
  `relation` enum('prerequisite','strong','related') NOT NULL DEFAULT 'related',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`source_node_id`,`target_node_id`,`relation`),
  KEY `idx_knowledge_edge_target` (`target_node_id`),
  CONSTRAINT `fk_knowledge_edge_source` FOREIGN KEY (`source_node_id`) REFERENCES `knowledge_node` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_knowledge_edge_target` FOREIGN KEY (`target_node_id`) REFERENCES `knowledge_node` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `knowledge_node_category` (
  `node_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`node_id`,`category_id`),
  KEY `idx_knowledge_node_category_category` (`category_id`),
  CONSTRAINT `fk_knowledge_node_category_node` FOREIGN KEY (`node_id`) REFERENCES `knowledge_node` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_knowledge_node_category_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `knowledge_node_tag_alias` (
  `node_id` int(11) NOT NULL,
  `tag_text` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`node_id`,`tag_text`),
  KEY `idx_knowledge_node_tag_alias_tag` (`tag_text`),
  CONSTRAINT `fk_knowledge_node_tag_alias_node` FOREIGN KEY (`node_id`) REFERENCES `knowledge_node` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @knowledge_graph_seed_required := (SELECT COUNT(*) = 0 FROM `knowledge_node`);
SET @knowledge_graph_coverage_v2_required := NOT EXISTS (SELECT 1 FROM `knowledge_node` WHERE `slug`='number-theory');
START TRANSACTION;

INSERT IGNORE INTO `knowledge_node` (`slug`,`name`,`domain`,`description`,`sort_order`) VALUES
('input-output','输入输出','编程基础','掌握标准输入输出、格式控制以及常见输入解析方法。',10),
('sequence','顺序结构','编程基础','理解语句按顺序执行以及表达式求值的基本过程。',20),
('selection','选择结构','编程基础','使用 if、else 和 switch 表达条件分支。',30),
('loops','循环结构','编程基础','使用 for、while 与循环控制完成重复计算。',40),
('functions','函数','编程基础','通过函数拆分任务，理解参数、返回值和作用域。',50),
('recursion','递归','编程基础','理解递归定义、终止条件和递归调用栈。',60),
('arrays','数组','编程基础','使用一维、多维和字符数组组织连续数据。',70),
('pointers','指针','编程基础','理解地址、指针运算以及指针与数组的关系。',80),
('structures','结构体','编程基础','使用结构体组织复合数据并完成记录处理。',90),
('macros','宏与预处理','编程基础','理解宏、条件编译和头文件等预处理机制。',100),
('bit-operations','位运算','编程基础','掌握按位与、或、异或、取反和移位，并用二进制表示状态。',110),

('algorithmic-thinking','算法思维','基础算法','学习建模、复杂度估算、构造和常见问题转化方法。',5),
('simulation','模拟','基础算法','把题意中的过程准确转换为可执行步骤。',10),
('enumeration','枚举','基础算法','系统遍历候选状态，并通过约束缩小搜索空间。',20),
('sorting','排序','基础算法','掌握常见排序方法、比较规则及其复杂度。',30),
('binary-search','查找与二分','基础算法','在有序空间中查找答案或边界。',40),
('prefix-sum','前缀和','基础算法','通过预处理快速回答区间聚合问题。',50),
('difference','差分','基础算法','使用差分结构高效处理区间修改。',60),
('two-pointers','双指针','基础算法','利用单调性或区间性质降低枚举复杂度。',70),
('greedy','贪心','基础算法','通过局部最优选择构造全局解，并判断适用条件。',80),
('divide-conquer','分治','基础算法','将问题拆成子问题，递归求解后合并结果。',90),
('stl','STL','基础算法','使用标准容器、算法和迭代器可靠实现算法。',100),

('data-structure-basics','数据结构基础','数据结构','理解数据组织、操作接口与时间空间复杂度。',5),
('linear-list','线性表','数据结构','理解顺序表和线性结构的基本操作。',10),
('linked-list','链表','数据结构','使用结点和指针实现动态线性结构。',20),
('stack','栈','数据结构','利用后进先出结构处理表达式、括号和搜索状态。',30),
('queue','队列','数据结构','利用先进先出结构组织任务和搜索层次。',40),
('strings','字符串','数据结构','掌握字符串存储、遍历与常见处理方法。',50),
('hash-table','哈希表','数据结构','使用哈希映射实现快速查找与计数。',60),
('heap','堆与优先队列','数据结构','维护动态最值并支持优先级调度。',70),
('tree','树','数据结构','理解树的表示、遍历和递归结构。',80),
('graph','图','数据结构','理解图的存储、遍历和基本建模方法。',90),
('union-find','并查集','数据结构','高效维护动态连通关系和集合合并。',100),
('monotonic-queue','单调队列','数据结构','维护滑动区间最值并优化动态规划转移。',110),
('monotonic-stack','单调栈','数据结构','维护单调序列并在线性时间寻找左右边界。',120),

('search','搜索','搜索','使用深度优先和广度优先搜索遍历状态空间。',10),
('backtracking','回溯','搜索','通过选择、递归和撤销系统探索解空间。',20),

('dynamic-programming','动态规划','动态规划','通过状态、转移和边界解决具有重叠子问题的任务。',10),
('state-compression','状态压缩 DP','动态规划','用位集合表示组合状态并进行动态规划。',20),
('interval-dp','区间 DP','动态规划','以区间为状态组织枚举顺序和合并转移。',30),
('recurrence','递推','动态规划','从已知边界按确定顺序推导后续状态。',40),

('math','数学基础','数学','使用数论、组合和代数知识建立算法模型。',10),
('fast-power','快速幂','数学','在对数复杂度内完成幂运算及模运算。',20),
('big-integer','大数运算','数学','用数组或字符串实现超出机器整数范围的运算。',30),
('computational-geometry','计算几何','数学','处理点、线、向量、距离和几何关系。',40),
('number-theory','数论','数学','掌握整除、素数、因子与同余等整数性质。',50),
('modular-arithmetic','模运算','数学','在模意义下完成加减乘幂和同余变换。',60),
('gcd','最大公约数','数学','使用欧几里得算法求最大公约数并理解整除关系。',70),
('extended-gcd','扩展欧几里得','数学','求解贝祖等式并处理线性同余方程。',80),
('modular-inverse','乘法逆元','数学','在模意义下求逆元并处理模除法。',90),
('chinese-remainder','中国剩余定理','数学','合并一组同余方程并求解同余方程组。',100),
('combinatorics','组合数学','数学','使用排列组合、计数原理和常见组合恒等式。',110),
('matrix-operations','矩阵运算','数学','掌握矩阵乘法及其在状态转移中的表示。',120),
('matrix-fast-power','矩阵快速幂','数学','结合矩阵乘法和快速幂加速线性递推。',130),
('game-theory','博弈论','数学','通过必胜态、必败态和基本博弈模型分析策略。',140),
('base-conversion','进制转换','数学','在不同进制表示之间可靠转换整数和数位。',150),
('numerical-methods','数值方法','数学','使用迭代、二分等方法近似求解数值问题。',160),

('string-matching','字符串匹配','字符串算法','在文本中高效定位模式串。',10),
('ac-automaton','AC 自动机','字符串算法','使用 Trie 与失配指针进行多模式匹配。',20),

('discretization','离散化','进阶专题','压缩稀疏坐标并保持相对顺序或等价关系。',10),
('interval-merge','区间合并','进阶专题','排序并合并重叠区间。',20),
('advanced-data-structures','高级数据结构','进阶专题','学习支持复杂区间和动态查询的数据结构。',30),
('kd-tree','KD-Tree','进阶专题','在多维空间中组织数据并完成近邻或范围查询。',40),
('sweep-line','扫描线','进阶专题','按事件顺序扫描坐标轴并维护动态截面信息。',50);

INSERT IGNORE INTO `knowledge_edge` (`source_node_id`,`target_node_id`,`relation`)
SELECT s.id,t.id,e.relation
FROM (
  SELECT 'input-output' s,'sequence' t,'prerequisite' relation UNION ALL
  SELECT 'sequence','selection','prerequisite' UNION ALL
  SELECT 'selection','loops','prerequisite' UNION ALL
  SELECT 'loops','functions','prerequisite' UNION ALL
  SELECT 'functions','recursion','prerequisite' UNION ALL
  SELECT 'loops','arrays','prerequisite' UNION ALL
  SELECT 'arrays','pointers','prerequisite' UNION ALL
  SELECT 'pointers','structures','related' UNION ALL
  SELECT 'functions','simulation','prerequisite' UNION ALL
  SELECT 'loops','enumeration','prerequisite' UNION ALL
  SELECT 'arrays','sorting','prerequisite' UNION ALL
  SELECT 'sorting','binary-search','prerequisite' UNION ALL
  SELECT 'arrays','prefix-sum','prerequisite' UNION ALL
  SELECT 'prefix-sum','difference','prerequisite' UNION ALL
  SELECT 'arrays','two-pointers','prerequisite' UNION ALL
  SELECT 'selection','greedy','prerequisite' UNION ALL
  SELECT 'recursion','divide-conquer','prerequisite' UNION ALL
  SELECT 'arrays','linear-list','prerequisite' UNION ALL
  SELECT 'pointers','linked-list','prerequisite' UNION ALL
  SELECT 'linear-list','stack','related' UNION ALL
  SELECT 'linear-list','queue','related' UNION ALL
  SELECT 'arrays','strings','prerequisite' UNION ALL
  SELECT 'arrays','hash-table','prerequisite' UNION ALL
  SELECT 'tree','heap','strong' UNION ALL
  SELECT 'recursion','tree','prerequisite' UNION ALL
  SELECT 'tree','graph','related' UNION ALL
  SELECT 'graph','union-find','related' UNION ALL
  SELECT 'queue','monotonic-queue','prerequisite' UNION ALL
  SELECT 'recursion','search','prerequisite' UNION ALL
  SELECT 'search','backtracking','strong' UNION ALL
  SELECT 'recursion','dynamic-programming','related' UNION ALL
  SELECT 'dynamic-programming','state-compression','prerequisite' UNION ALL
  SELECT 'loops','fast-power','prerequisite' UNION ALL
  SELECT 'arrays','big-integer','prerequisite' UNION ALL
  SELECT 'strings','string-matching','prerequisite' UNION ALL
  SELECT 'string-matching','ac-automaton','prerequisite' UNION ALL
  SELECT 'sorting','discretization','prerequisite' UNION ALL
  SELECT 'sorting','interval-merge','prerequisite' UNION ALL
  SELECT 'tree','advanced-data-structures','prerequisite' UNION ALL
  SELECT 'advanced-data-structures','kd-tree','prerequisite'
) e
JOIN `knowledge_node` s ON s.slug=e.s
JOIN `knowledge_node` t ON t.slug=e.t
WHERE @knowledge_graph_seed_required = 1;

INSERT IGNORE INTO `knowledge_edge` (`source_node_id`,`target_node_id`,`relation`)
SELECT s.id,t.id,e.relation
FROM (
  SELECT 'sequence' s,'bit-operations' t,'prerequisite' relation UNION ALL
  SELECT 'algorithmic-thinking','simulation','related' UNION ALL
  SELECT 'algorithmic-thinking','enumeration','related' UNION ALL
  SELECT 'data-structure-basics','linear-list','prerequisite' UNION ALL
  SELECT 'data-structure-basics','stack','prerequisite' UNION ALL
  SELECT 'data-structure-basics','queue','prerequisite' UNION ALL
  SELECT 'stack','monotonic-stack','prerequisite' UNION ALL
  SELECT 'monotonic-stack','monotonic-queue','related' UNION ALL
  SELECT 'dynamic-programming','interval-dp','prerequisite' UNION ALL
  SELECT 'recurrence','dynamic-programming','related' UNION ALL
  SELECT 'math','number-theory','prerequisite' UNION ALL
  SELECT 'number-theory','modular-arithmetic','prerequisite' UNION ALL
  SELECT 'number-theory','gcd','prerequisite' UNION ALL
  SELECT 'gcd','extended-gcd','prerequisite' UNION ALL
  SELECT 'extended-gcd','modular-inverse','prerequisite' UNION ALL
  SELECT 'extended-gcd','chinese-remainder','prerequisite' UNION ALL
  SELECT 'modular-arithmetic','chinese-remainder','prerequisite' UNION ALL
  SELECT 'math','combinatorics','prerequisite' UNION ALL
  SELECT 'arrays','matrix-operations','prerequisite' UNION ALL
  SELECT 'matrix-operations','matrix-fast-power','prerequisite' UNION ALL
  SELECT 'fast-power','matrix-fast-power','prerequisite' UNION ALL
  SELECT 'math','game-theory','prerequisite' UNION ALL
  SELECT 'math','base-conversion','related' UNION ALL
  SELECT 'math','numerical-methods','related' UNION ALL
  SELECT 'sorting','sweep-line','prerequisite' UNION ALL
  SELECT 'computational-geometry','sweep-line','related'
) e
JOIN `knowledge_node` s ON s.slug=e.s
JOIN `knowledge_node` t ON t.slug=e.t
WHERE @knowledge_graph_coverage_v2_required = 1;

INSERT IGNORE INTO `knowledge_node_category` (`node_id`,`category_id`)
SELECT n.id,c.id
FROM `knowledge_node` n
JOIN `category` c ON
  (n.slug='input-output' AND c.`content-1`='C语言' AND c.`content-2`='输入输出') OR
  (n.slug='sequence' AND c.`content-1`='C语言' AND c.`content-2`='顺序结构') OR
  (n.slug='selection' AND c.`content-1`='C语言' AND c.`content-2`='选择结构') OR
  (n.slug='loops' AND c.`content-1`='C语言' AND c.`content-2`='循环结构') OR
  (n.slug='functions' AND c.`content-1`='C语言' AND c.`content-2`='函数') OR
  (n.slug='recursion' AND c.`content-1`='C语言' AND c.`content-2`='递归') OR
  (n.slug='arrays' AND c.`content-1`='C语言' AND c.`content-2`='数组') OR
  (n.slug='pointers' AND c.`content-1`='C语言' AND c.`content-2`='指针') OR
  (n.slug='structures' AND c.`content-1`='C语言' AND c.`content-2`='结构体') OR
  (n.slug='macros' AND c.`content-1`='C语言' AND c.`content-2`='宏') OR
  (n.slug='simulation' AND c.`content-1`='算法设计' AND c.`content-2`='模拟') OR
  (n.slug='enumeration' AND c.`content-1`='ACM' AND c.`content-2`='枚举') OR
  (n.slug='sorting' AND c.`content-1`='算法设计' AND c.`content-2`='排序算法') OR
  (n.slug='binary-search' AND c.`content-1`='数据结构' AND c.`content-2`='查找与排序') OR
  (n.slug='prefix-sum' AND c.`content-1`='ACM' AND c.`content-2`='前缀和') OR
  (n.slug='difference' AND c.`content-1`='ACM' AND c.`content-2`='差分') OR
  (n.slug='two-pointers' AND c.`content-1`='算法设计' AND c.`content-2`='双指针') OR
  (n.slug='greedy' AND c.`content-1`='算法设计' AND c.`content-2`='贪心') OR
  (n.slug='divide-conquer' AND c.`content-1`='算法设计' AND c.`content-2`='递归分治') OR
  (n.slug='stl' AND c.`content-1`='算法设计' AND c.`content-2`='STL') OR
  (n.slug='linear-list' AND c.`content-1`='数据结构' AND c.`content-2`='线性表') OR
  (n.slug='linked-list' AND c.`content-1`='数据结构' AND c.`content-2`='链表') OR
  (n.slug='stack' AND c.`content-1`='数据结构' AND c.`content-2`='栈') OR
  (n.slug='queue' AND c.`content-1`='数据结构' AND c.`content-2`='队列') OR
  (n.slug='strings' AND c.`content-1`='数据结构' AND c.`content-2`='串') OR
  (n.slug='hash-table' AND c.`content-1`='数据结构' AND c.`content-2`='哈希表') OR
  (n.slug='heap' AND c.`content-1`='数据结构' AND c.`content-2`='二叉堆') OR
  (n.slug='tree' AND c.`content-1`='数据结构' AND c.`content-2`='树') OR
  (n.slug='graph' AND c.`content-1`='数据结构' AND c.`content-2`='图') OR
  (n.slug='union-find' AND c.`content-1`='ACM' AND c.`content-2`='并查集') OR
  (n.slug='monotonic-queue' AND c.`content-1`='ACM' AND c.`content-2`='单调队列') OR
  (n.slug='search' AND c.`content-1`='算法设计' AND c.`content-2`='搜索') OR
  (n.slug='backtracking' AND c.`content-1`='算法设计' AND c.`content-2`='回溯法') OR
  (n.slug='dynamic-programming' AND c.`content-1`='算法设计' AND c.`content-2`='动态规划') OR
  (n.slug='state-compression' AND c.`content-1`='ACM' AND c.`content-2`='状态压缩DP') OR
  (n.slug='math' AND c.`content-1`='ACM' AND c.`content-2`='数学') OR
  (n.slug='fast-power' AND c.`content-1`='ACM' AND c.`content-2`='快速幂') OR
  (n.slug='big-integer' AND c.`content-1`='ACM' AND c.`content-2`='大数运算') OR
  (n.slug='computational-geometry' AND c.`content-1`='ACM' AND c.`content-2`='计算几何') OR
  (n.slug='string-matching' AND c.`content-1`='算法设计' AND c.`content-2`='字符串匹配') OR
  (n.slug='ac-automaton' AND c.`content-1`='ACM' AND c.`content-2`='AC自动机') OR
  (n.slug='discretization' AND c.`content-1`='ACM' AND c.`content-2`='离散化') OR
  (n.slug='interval-merge' AND c.`content-1`='ACM' AND c.`content-2`='区间合并') OR
  (n.slug='advanced-data-structures' AND c.`content-1`='ACM' AND c.`content-2`='高级数据结构') OR
  (n.slug='kd-tree' AND c.`content-1`='ACM' AND c.`content-2`='KD-Tree')
WHERE @knowledge_graph_seed_required = 1;

INSERT IGNORE INTO `knowledge_node_category` (`node_id`,`category_id`)
SELECT n.id,c.id
FROM (
  SELECT 'data-structure-basics' slug,'数据结构' p,'' s UNION ALL
  SELECT 'binary-search','算法设计','查找排序' UNION ALL
  SELECT 'sorting','算法设计','查找排序' UNION ALL
  SELECT 'bit-operations','ACM','二进制' UNION ALL
  SELECT 'algorithmic-thinking','ACM','思维' UNION ALL
  SELECT 'algorithmic-thinking','AcWing','基础算法' UNION ALL
  SELECT 'data-structure-basics','AcWing','数据结构' UNION ALL
  SELECT 'math','AcWing','数学' UNION ALL
  SELECT 'algorithmic-thinking','CodeForces','思维题' UNION ALL
  SELECT 'algorithmic-thinking','ACM实验室训练集','基本算法' UNION ALL
  SELECT 'data-structure-basics','ACM实验室训练集','基本数据结构' UNION ALL
  SELECT 'math','ACM实验室训练集','数学知识' UNION ALL
  SELECT 'search','ACM实验室训练集','搜索'
) x
JOIN `knowledge_node` n ON n.slug=x.slug
JOIN `category` c ON TRIM(c.`content-1`)=x.p AND TRIM(BOTH CHAR(9) FROM TRIM(c.`content-2`))=x.s AND c.status=0
WHERE @knowledge_graph_coverage_v2_required = 1;

INSERT IGNORE INTO `knowledge_node_tag_alias` (`node_id`,`tag_text`)
SELECT n.id,a.tag_text
FROM (
  SELECT 'number-theory' slug,'数论' tag_text UNION ALL
  SELECT 'number-theory','欧拉函数' UNION ALL
  SELECT 'number-theory','质因数分解' UNION ALL
  SELECT 'number-theory','素数判定' UNION ALL
  SELECT 'greedy','贪心' UNION ALL
  SELECT 'greedy','入门题-贪心' UNION ALL
  SELECT 'greedy','基本算法-贪心算法' UNION ALL
  SELECT 'greedy','进阶题-贪心算法' UNION ALL
  SELECT 'fast-power','快速幂' UNION ALL
  SELECT 'fast-power','快速幂取模' UNION ALL
  SELECT 'modular-arithmetic','模运算' UNION ALL
  SELECT 'modular-arithmetic','取模运算' UNION ALL
  SELECT 'simulation','模拟' UNION ALL
  SELECT 'simulation','数学模拟' UNION ALL
  SELECT 'math','数学模拟' UNION ALL
  SELECT 'prefix-sum','前缀和' UNION ALL
  SELECT 'math','入门题-数学' UNION ALL
  SELECT 'math','数学' UNION ALL
  SELECT 'math','对数' UNION ALL
  SELECT 'binary-search','二分法' UNION ALL
  SELECT 'binary-search','二分查找' UNION ALL
  SELECT 'binary-search','二分' UNION ALL
  SELECT 'binary-search','查找算法' UNION ALL
  SELECT 'binary-search','静态查找' UNION ALL
  SELECT 'chinese-remainder','中国剩余定理' UNION ALL
  SELECT 'chinese-remainder','同余方程组' UNION ALL
  SELECT 'modular-arithmetic','同余方程' UNION ALL
  SELECT 'dynamic-programming','动态规划' UNION ALL
  SELECT 'dynamic-programming','动态规划-线性动归' UNION ALL
  SELECT 'interval-dp','区间DP' UNION ALL
  SELECT 'big-integer','高精度' UNION ALL
  SELECT 'big-integer','基本算法-高精度' UNION ALL
  SELECT 'matrix-fast-power','矩阵快速幂' UNION ALL
  SELECT 'matrix-operations','矩阵乘法' UNION ALL
  SELECT 'sorting','排序' UNION ALL
  SELECT 'sorting','基础算法-排序' UNION ALL
  SELECT 'sorting','去重' UNION ALL
  SELECT 'divide-conquer','分治法' UNION ALL
  SELECT 'divide-conquer','分治' UNION ALL
  SELECT 'enumeration','枚举' UNION ALL
  SELECT 'enumeration','常用算法-枚举算法' UNION ALL
  SELECT 'recursion','递归' UNION ALL
  SELECT 'monotonic-stack','单调栈' UNION ALL
  SELECT 'recurrence','递推' UNION ALL
  SELECT 'numerical-methods','数值分析' UNION ALL
  SELECT 'numerical-methods','方程求根' UNION ALL
  SELECT 'numerical-methods','迭代法' UNION ALL
  SELECT 'input-output','c语言-输入输出' UNION ALL
  SELECT 'stack','入门题-栈' UNION ALL
  SELECT 'bit-operations','入门题-位运算' UNION ALL
  SELECT 'search','入门题-搜索' UNION ALL
  SELECT 'search','搜索' UNION ALL
  SELECT 'union-find','入门题-并查集' UNION ALL
  SELECT 'computational-geometry','入门题-计算几何' UNION ALL
  SELECT 'algorithmic-thinking','算法设计-简单算法' UNION ALL
  SELECT 'linear-list','有序表' UNION ALL
  SELECT 'selection','程序设计基础2014-选择控制结构' UNION ALL
  SELECT 'extended-gcd','扩展欧几里得算法' UNION ALL
  SELECT 'gcd','欧几里得算法' UNION ALL
  SELECT 'modular-inverse','逆元' UNION ALL
  SELECT 'game-theory','入门题-博弈' UNION ALL
  SELECT 'game-theory','博弈论' UNION ALL
  SELECT 'combinatorics','组合数学' UNION ALL
  SELECT 'interval-merge','区间问题' UNION ALL
  SELECT 'discretization','离散化' UNION ALL
  SELECT 'monotonic-queue','单调队列' UNION ALL
  SELECT 'monotonic-queue','二维滑动窗口' UNION ALL
  SELECT 'backtracking','基本算法-回溯算法' UNION ALL
  SELECT 'backtracking','一本通2018-第五章-搜索与回溯算法' UNION ALL
  SELECT 'base-conversion','进制转换' UNION ALL
  SELECT 'arrays','高级语言程序设计I-第11章：指针和数组' UNION ALL
  SELECT 'pointers','高级语言程序设计I-第11章：指针和数组' UNION ALL
  SELECT 'sweep-line','扫描线' UNION ALL
  SELECT 'two-pointers','双指针' UNION ALL
  SELECT 'arrays','数组处理' UNION ALL
  SELECT 'difference','差分数组'
) a
JOIN `knowledge_node` n ON n.slug=a.slug
WHERE @knowledge_graph_coverage_v2_required = 1;

COMMIT;
