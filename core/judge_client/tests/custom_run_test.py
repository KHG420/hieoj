#!/usr/bin/env python3
"""Compile the actual test_run body with process/DB stubs; no untrusted execution.
This exercises result persistence and file handling, not Linux ptrace isolation.
"""
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

source = Path(__file__).resolve().parents[1] / 'judge_client.cc'
text = source.read_text()
body = text[text.index('int test_run('):text.index('\nint main(', text.index('int test_run('))]
constants = '\n'.join(re.findall(r'^#define (?:OJ_\w+|CUSTOM_OUTPUT_LIMIT) .*$', text, re.M))
harness = r'''
#include <cassert>
#include <cstdio>
#include <cstring>
#include <string>
#include <sys/stat.h>
using pid_t = int;
const int DEBUG=0, LANG_CJ=22;
int fake_result=4, saved=-1, captured=0, fork_result=123;
size_t prelen=16;
long get_file_size(const char *p) { struct stat s; return stat(p,&s)==0?s.st_size:0; }
void get_custominput(int,char*) {}
void init_syscalls_limits(int) {}
int fake_fork() { return fork_result; }
#define fork fake_fork
void run_solution(int,char*,double,int&,int,char*,int) { assert(false); }
void watch_solution(int,char *input,int &result,int,char *user,char *expected,int,int,int&,int,int&,double,int,int&,char*) {
  assert(strcmp(input,"data.in")==0);
  assert(prelen==0);
  assert(strcmp(user,"user.out")==0);
  assert(strcmp(expected,"/dev/null")==0);
  result=fake_result;
}
void addreinfo(int) { captured=1; }
void addcustomout(int) { captured=2; }
void update_solution(int,int result,int,int,int,int,int) { saved=result; }
void clean_workdir(char*) {}
'''
checks = r'''
void writefile(const char *path,const std::string &data) {
 FILE *f=fopen(path,"wb"); assert(f); fwrite(data.data(),1,data.size(),f); fclose(f);
}
void check(int actual,int expected,const std::string &out="42\n",const std::string &err="",int language=1) {
 writefile("user.out",out); writefile("error.out",err);
 fake_result=actual; saved=-1; captured=0;
 char user[256],input[256],output[256],dir[]=".";
 memset(user,0x55,sizeof user); memset(input,0x55,sizeof input); memset(output,0x55,sizeof output);
 int time=0,memory=0,pe=OJ_AC,result=OJ_AC;
 test_run(1,0,language,dir,5,time,512,user,input,output,memory,0,pe,result);
 assert(saved==expected);
}
int main() {
 check(OJ_AC,OJ_TR);
 check(OJ_AC,OJ_TR,"");
 check(OJ_AC,OJ_TR,std::string(CUSTOM_OUTPUT_LIMIT,'a'));
 check(OJ_AC,OJ_OL,std::string(CUSTOM_OUTPUT_LIMIT+1,'a'));
 check(OJ_AC,OJ_OL,std::string("4\0hidden",8));
 for(int result: {OJ_TL,OJ_ML,OJ_OL,OJ_RE}) { check(result,result); check(result,result,"","stderr"); }
 check(OJ_AC,OJ_RE,"42","runtime failure"); assert(captured==1);
 check(OJ_AC,OJ_TR,"42","ignored",LANG_CJ); assert(captured==2);
 fork_result=-1; check(OJ_AC,OJ_MC);
 puts("PASS: actual custom-run result, paths, empty/output limits, NUL, stderr, fork failure (16 cases)");
}
'''
compiler = shutil.which('clang++') or shutil.which('g++')
if not compiler:
    raise SystemExit('C++ compiler required')
with tempfile.TemporaryDirectory(prefix='hunt-custom-run-') as d:
    d = Path(d)
    (d/'test.cc').write_text(constants+'\n'+harness+'\n'+body+'\n'+checks)
    subprocess.run([compiler,'-std=c++11','-Wall','-Wextra','-Werror',str(d/'test.cc'),'-o',str(d/'test')],check=True)
    subprocess.run([str(d/'test')],cwd=d,check=True)
