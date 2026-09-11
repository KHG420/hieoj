<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
<!--  <meta name="viewport" content="width=device-width, initial-scale=1">-->
  <meta name="description" content="">
  <meta name="author" content="">

  <title><?php echo $OJ_NAME?></title>
  <link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
  <link rel="stylesheet" href="./template/sta_sty/css/prism.css">

  <script src="./template/sta_sty/layui/layui.js"></script>
  <script src="./template/sta_sty/js/prism.js"></script>
</head>

<body>

  <div class="container">
      <?php include("template/$OJ_TEMPLATE/header.php");?>
  </div>
  <div style="background-color: #f2f2f2;">
    <div style="padding:15px;">
      <div class="layui-row layui-col-space15">
        <div class="layui-col-md12">
          <div class="layui-panel">
            <div style="padding:20px;">
              <center>
                <div style="font-size: 36px;">Online Judge FAQ</div>
              </center>
            </div>
          </div>
          <div class="layui-panel">
            <div style="padding: 20px 30px;">
                <fieldset class="layui-elem-field">
                    <legend style="font-size: 24px;"></legend>
                    <div class="layui-field-box">
                        <div style="font-size: 18px; margin-left: 20px;">
                            <p>
                                本系统由ACM实验室负责日常管理与维护<br>
                                若需反馈bug与测试数据问题可在讨论版提出<br>
                                <a href="about.php"></a>
                            </p>
                        </div>
                </fieldset>
              <fieldset class="layui-elem-field">
                <legend style="font-size: 23px;">判题系统的编译器与编译选项</legend>
                <div class="layui-field-box">
                  <div style="font-size: 18px;">
                    <p>
                      系统运行于<a href="http://www.debian.org/">Debian</a>/<a href="http://www.ubuntu.com">Ubuntu</a>Linux.
                      使用<a href="http://gcc.gnu.org/">GNU GCC/G++</a> 作为C/C++编译器,
                      <a href="http://www.freepascal.org">Free Pascal</a> 作为pascal 编译器 ，用
                      <a href="http://openjdk.java.net/">openjdk-7</a> 编译 Java.<br>
                      对应的编译选项如下:<br>
                    </p>
                    <div class="layui-form">
                      <table class="layui-table" lay-even="" lay-skin="row">
                        <thead>
                          <tr>
                            <th>语言</th>
                            <th>编译选项</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>C:</td>
                            <td>
                              <font color=blue>gcc Main.c -o Main -fno-asm -Wall -lm --static -std=c99 -DONLINE_JUDGE
                              </font>
                            </td>
                          </tr>
                          <tr>
                            <td>C++:</td>
                            <td>
                              <font color=blue>g++ -fno-asm -Wall -lm --static -std=c++11 -DONLINE_JUDGE -o Main Main.cc
                              </font>
                            </td>
                          </tr>
                          <tr>
                            <td>Pascal:</td>
                            <td>
                              <font color=blue>fpc Main.pas -oMain -O1 -Co -Cr -Ct -Ci </font>
                            </td>
                          </tr>
                          <tr>
                            <td>Java:</td>
                            <td>
                              <font color="blue">javac -J-Xms32m -J-Xmx256m Main.java</font>
                              <br>
                              <font size="-1" color="red">*Java has 2 more seconds and 512M more memory when running and
                                judging.</font>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p>
                      编译器版本为（系统可能升级编译器版本，这里仅供参考）:<br>
                      <font color=blue>gcc version 4.8.4 (Ubuntu 4.8.4-2ubuntu1~14.04.3)</font><br>
                      <font color=blue>glibc 2.19</font><br>
                      <font color=blue>Free Pascal Compiler version 2.6.2<br>
                        openjdk 1.7.0_151<br>
                      </font>
                    </p>
                  </div>
                </div>
              </fieldset>
              <fieldset class="layui-elem-field">
                <legend style="font-size: 24px;">编写程序的输入输出</legend>
                <div class="layui-field-box">
                  <div style="font-size: 18px;">
                    <p>
                      所编写的程序程序应该从标准输入 stdin('Standard Input')获取输入，并将结果输出到标准输出 stdout('Standard Output')<br>
                      例如:在C语言可以使用 'scanf' ，在C++可以使用'cin' 进行输入；在C使用 'printf' ，在C++使用'cout'进行输出<br>
                      用户程序不允许直接读写文件, 如果这样做可能会判为运行时错误 "<font color=green>Runtime Error</font>"
                    </p>
                    <p>
                      下面是 1004题的参考答案:
                    </p>
                    <div style="margin: 10px;">
                      <div style="font-size: 20px;">C++:</div>
<pre><code class="language-c++" style="font-size: 14px;">#include &lt;iostream&gt;
using namespace std;
int main(){
    int a,b;
    while(cin >> a >> b)
        cout << a+b << endl;
    return 0;
}</code></pre>
                    </div>
                    <div style="margin: 10px;">
                      <div style="font-size: 20px;">C:</div>
<pre><code class="language-c" style="font-size: 14px;">#include &lt;stdio.h&gt;
int main(){
int a,b;
while(scanf("%d %d",&amp;a, &amp;b) != EOF)
    printf("%d\n",a+b);
return 0;
}</code></pre>
                    </div>
                    <div style="margin: 10px;">
                      <div style="font-size: 20px;">PASCAL:</div>
<pre><code class="language-pascal" style="font-size: 14px;">program p1001(Input,Output); 
var 
  a,b:Integer; 
begin 
  while not eof(Input) do 
    begin 
      Readln(a,b); 
      Writeln(a+b); 
    end; 
end.</code></pre>
                    </div>
                    <div style="margin: 10px;">
                      <div style="font-size: 20px;">Java:</div>
<pre><code class="language-java" style="font-size: 14px;">import java.util.*;
public class Main{
  public static void main(String args[]){
    Scanner cin = new Scanner(System.in);
    int a, b;
    while (cin.hasNext()){
      a = cin.nextInt(); b = cin.nextInt();
      System.out.println(a + b);
    }
  }
}</code></pre>
                    </div>
                  </div>
                </div>
              </fieldset>
              <fieldset class="layui-elem-field">
                <legend style="font-size: 24px;">长整型输入</legend>
                <div class="layui-field-box">
                  <div style="font-size: 18px;">
                    <font color=green>__int64</font>不是ANSI标准定义，只能在VC使用, 但是可以使用<font color=blue>long long</font>声明64位整数。<br>
                    如果用了<font color=green>__int64</font>,试试提交前加一句<font color=blue>#define __int64 long long</font>, scanf和printf 请使用%lld作为格式
                  </div>
              </fieldset>
              <fieldset class="layui-elem-field">
                <legend style="font-size: 24px;">系统评测信息</legend>
                <div class="layui-field-box">
                  <div style="font-size: 18px;">
                    <div class="layui-form">
                      <table class="layui-table" lay-even="" lay-skin="row">
                        <tr>
                          <td><font color=blue>Pending</font></td>
                          <td>系统忙，你的答案在排队等待</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Pending Rejudge</font></td>
                          <td>因为数据更新或其他原因，系统将重新判你的答案</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Running &amp; Judging</font></td>
                          <td>正在运行和判断</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Accepted</font></td>
                          <td>程序通过!</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Presentation Error</font></td>
                          <td>答案基本正确，但是格式不对</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Wrong Answer</font></td>
                          <td>答案不对，仅仅通过样例数据的测试并不一定是正确答案，一定还有你没想到的地方</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Time Limit Exceeded</font></td>
                          <td>运行超出时间限制，检查下是否有死循环，或者应该有更快的计算方法</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Memory Limit Exceeded</font></td>
                          <td>超出内存限制，数据可能需要压缩，检查内存是否有泄露</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Output Limit Exceeded</font></td>
                          <td>输出超过限制，你的输出比正确答案长</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Runtime Error</font></td>
                          <td>运行时错误，非法的内存访问，数组越界，指针漂移，调用禁用的系统函数。请点击后获得详细输出</td>
                        </tr>
                        <tr>
                          <td><font color=blue>Compile Error</font></td>
                          <td>编译错误，请点击后获得编译器的详细输出</td>
                        </tr>
                      </table>
                    </div>
                  </div>
              </fieldset>
            </div>
          </div>
        </div>
      </div>
    </div>
</body>

</html>
