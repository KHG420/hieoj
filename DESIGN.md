---
name: HNIE OJ — ACM 实验室扩展
description: 继承蓝白 Semantic UI 的操作与阅读界面；仅记录实验室、首页入口和排名范围筛选。
colors:
  primary: "#245dd8"
  primary-hover: "#1d4db8"
  surface: "#fff"
  background: "#f4f6fa"
  text: "#25344a"
  border: "#dce3ec"
  field-border: "#c9d3e0"
  muted: "#596a82"
typography:
  headline:
    fontSize: "32px"
    letterSpacing: "-.02em"
  title:
    fontSize: "24px"
    lineHeight: 1.5
rounded:
  panel: "12px"
  field: "6px"
spacing:
  form-gap: "12px"
  panel: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
    padding: "{spacing.panel}"
  field:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.field}"
    padding: "10px 12px"
---

# Design System: HNIE OJ — ACM 实验室扩展

## Overview

**Creative North Star: "延续现有蓝白工作界面"**

这是已实现界面的有限记录，不是新的全站视觉方案，也没有单独批准的视觉效果图。实验室以操作（Operate）与阅读（Read）为主：清楚的中文标签、白色面板、蓝色主要动作和可查询的文字状态。继承现有 Semantic UI 与桌面样式。

依据 `PRODUCT.md`、实验室模板及样式、首页实验室模块、排名范围筛选和桌面／手机审阅截图。未重新定义其他页面。

## Colors

主要蓝用于操作、链接和选中标签；白色面板承载浅色背景上的内容。深色文字与弱化的辅助说明建立信息层次。输入边框比面板边框明确。状态使用可读文字，不依赖颜色判别。

## Typography

继承站点中文无衬线字体；桌面系统字体栈来自共享桌面样式。页面标题在窄屏降为（28px），区块标题保持紧凑。介绍和回复保留换行，阅读行高（1.85），最大行长（75ch）；记录字段行高（1.8）。表单标签使用加重字重，必填、选填和字数限制紧邻标签。

## Layout

实验室页面最大宽度（1100px），单列表单最大宽度（760px）。标题、可换行的标签导航、正文面板顺序固定。桌面面板内边距使用 panel；屏宽不超过（999px）时页面取消旧最小宽度，外侧留（16px），全站顶部导航可水平滚动；标签导航换行，操作区竖排。屏宽不超过（600px）时实验室面板内边距（20px），列表状态可换行，筛选项可伸展。

首页实验室模块沿用现有卡片，四个入口可换行，按钮间距（8px）。首页整体旧固定宽度行为不属于本次设计。

排名沿用单一页面标题、白色筛选面板和可水平滚动的表格。范围控件与学院、年级、班级及姓名／学号组成一组有可见标签的筛选条件；范围说明在筛选上方。分页只显示当前页附近的有限页码及前后入口，不铺满历史页码。

## Elevation & Depth

实验室面板靠浅边框区分，没有新增阴影。首页和排名保留各自现有卡片层次，不把这些差异扩展成新的全局规则。

## Shapes

面板使用较柔和的圆角，输入框使用较小圆角；输入保留明确的细描边。列表用分隔线组织，状态是轻量文字，不另造彩色徽章。

## Components

- **按钮：**沿用 Semantic UI 主要／基础按钮；实验室主要按钮使用 primary，悬停使用 primary-hover，最小高度（44px）。提交按钮置于表单末尾。
- **输入：**单列、全宽、显式标签；文本域允许纵向调整。实验室链接和输入的键盘焦点使用（2px）蓝色轮廓和（3px）偏移；按钮继承编辑页面的（3px）轮廓。
- **导航：**选中项同时使用蓝色、加重文字和底部线，并有 `aria-current`。实验室管理员入口仅在相应权限下出现。
- **记录与详情：**列表呈现标题、类型、时间、文字状态；详情先显示内容与回复，再显示管理员处理表单。类型、状态筛选分别有标签。
- **反馈消息：**保存结果使用状态消息，错误使用警告语义；失败时保留文本，截图需重新选择的说明紧邻附件控件。
- **排名范围：**默认“最近一年”，另一项“全部时间”；选择即提交现有筛选表单。说明文字解释区间与同题重复通过去重。

## Do's and Don'ts

- **Do** 延续现有蓝白 Semantic UI、可见标签、文字状态和清楚的主要动作。
- **Do** 为长标题与纯文本保留换行和断行，为手机表格保留内部横向滚动。
- **Don't** 把本次实验室布局规则推广成全站重设计。
- **Don't** 为状态增加没有文字解释的颜色编码，或用占位符替代字段标签。
