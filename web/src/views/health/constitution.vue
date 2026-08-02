<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CircleCheckFilled, StarFilled } from '@element-plus/icons-vue'

const router = useRouter()

interface ConstitutionType {
  name: string
  score: number
  description: string
  features: string[]
  color: string
  advice: {
    diet: string
    exercise: string
    lifestyle: string
  }
}

const primaryConstitution = ref<ConstitutionType | null>(null)
const constitutionList = ref<ConstitutionType[]>([])
const loading = ref(false)
const lastTestDate = ref('')

const getToken = (): string => localStorage.getItem('token') || ''

// 九种体质的元数据（描述/特征/颜色是中医知识库，不算业务硬编码）
const CONSTITUTION_META: Record<string, { description: string; features: string[]; color: string; advice: ConstitutionType['advice'] }> = {
  '气虚质': { description: '元气不足，以疲乏、气短、自汗等表现为主要特征。', features: ['容易疲乏', '气短懒言', '易出汗', '精神不振'], color: '#ee0a24', advice: { diet: '宜食用益气健脾食物，如山药、大枣、莲子等。', exercise: '宜选择柔和运动，如太极拳、八段锦、散步等。', lifestyle: '保持规律作息，避免过度劳累，注意保暖。' } },
  '阳虚质': { description: '阳气不足，以畏寒怕冷、手足不温等表现为主要特征。', features: ['畏寒怕冷', '手足不温', '喜热饮食', '精神不振'], color: '#1989fa', advice: { diet: '宜温阳补肾食物，如羊肉、生姜、桂圆等。', exercise: '宜温和运动，避免大汗淋漓。', lifestyle: '注意保暖，避免受凉。' } },
  '阴虚质': { description: '阴液亏少，以口燥咽干、手足心热等表现为主要特征。', features: ['口燥咽干', '手足心热', '喜冷饮', '大便干燥'], color: '#ff976a', advice: { diet: '宜养阴润燥食物，如银耳、百合、梨等。', exercise: '避免高温剧烈运动。', lifestyle: '保证充足睡眠，避免熬夜。' } },
  '痰湿质': { description: '痰湿凝聚，以形体肥胖、腹部肥满、口黏苔腻等为主要特征。', features: ['形体肥胖', '腹部肥满', '口黏苔腻', '面部油脂多'], color: '#7232dd', advice: { diet: '清淡饮食，少糖少油，多吃薏苡仁、冬瓜。', exercise: '加强运动，控制体重。', lifestyle: '远离潮湿环境。' } },
  '湿热质': { description: '湿热内蕴，以面垢油腻、口苦苔黄腻等为主要特征。', features: ['面垢油腻', '口苦口干', '身重困倦', '大便黏滞'], color: '#f2826a', advice: { diet: '清淡饮食，多吃绿豆、苦瓜、薏米。', exercise: '多运动出汗以排湿。', lifestyle: '戒烟限酒，避免熬夜。' } },
  '血瘀质': { description: '血行不畅，以肤色晦暗、舌质紫暗等为主要特征。', features: ['肤色晦暗', '色素沉着', '易有瘀斑', '口唇暗淡'], color: '#969799', advice: { diet: '多食活血化瘀食物，如山楂、红花、玫瑰花。', exercise: '多运动促进血液循环。', lifestyle: '保持心情舒畅。' } },
  '气郁质': { description: '气机郁滞，以神情抑郁、忧虑脆弱等为主要特征。', features: ['神情抑郁', '情绪不宁', '胸胁胀满', '善太息'], color: '#07c160', advice: { diet: '多食疏肝理气食物，如陈皮、玫瑰、柑橘。', exercise: '多户外活动，结伴运动。', lifestyle: '保持心情舒畅，多听音乐。' } },
  '特禀质': { description: '先天失常，以生理缺陷、过敏反应等为主要特征。', features: ['过敏体质', '遗传性疾病', '先天畸形', '五迟五软'], color: '#323233', advice: { diet: '避免已知过敏食物。', exercise: '适度运动增强体质。', lifestyle: '远离过敏原，注意季节变化。' } },
  '平和质': { description: '阴阳气血调和，以体态适中、面色红润、精力充沛等为主要特征。', features: ['体态适中', '面色红润', '精力充沛', '睡眠良好'], color: '#07c160', advice: { diet: '保持均衡饮食。', exercise: '规律运动，劳逸结合。', lifestyle: '保持良好作息和心情。' } },
}

// 从后端加载体质数据（不带任何硬编码）
const fetchConstitutionData = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/health/constitution', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const result = data.data || {}
      const history = result.history || []
      lastTestDate.value = result.last_test_at || ''

      // 优先用最新一次测试的 scores，否则用空对象
      let scores: Record<string, number> = {}
      if (history[0]?.scores) {
        scores = history[0].scores
      }

      // 派生体质列表
      const list: ConstitutionType[] = Object.keys(CONSTITUTION_META).map(name => {
        const meta = CONSTITUTION_META[name]
        return {
          name,
          score: Math.round(Number(scores[name]) || 0),
          description: meta.description,
          features: meta.features,
          color: meta.color,
          advice: meta.advice,
        }
      })
      // 按分数降序
      list.sort((a, b) => b.score - a.score)
      constitutionList.value = list
      primaryConstitution.value = list[0] || null
    } else {
      ElMessage.error(data.message || '获取体质数据失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误，请稍后重试')
  } finally {
    loading.value = false
  }
}

const goToTest = () => {
  router.push('/constitution/test')
}

const getScoreLevel = (score: number) => {
  if (score >= 60) return '明显'
  if (score >= 40) return '倾向'
  if (score >= 20) return '轻度'
  return '无'
}

const getScoreLevelColor = (score: number) => {
  if (score >= 60) return '#ee0a24'
  if (score >= 40) return '#ff976a'
  if (score >= 20) return '#ffc107'
  return '#07c160'
}

onMounted(() => {
  fetchConstitutionData()
})
</script>

<template>
  <div class="constitution-page" v-loading="loading" element-loading-text="加载中...">
    <template v-if="!loading">
      <!-- 主要体质卡片 -->
      <div v-if="primaryConstitution" class="primary-card">
        <div class="primary-header">
          <div class="primary-label">主要体质</div>
          <div class="test-date">最近测试：{{ lastTestDate }}</div>
        </div>
        <div class="primary-name" :style="{ color: primaryConstitution.color }">
          {{ primaryConstitution.name }}
        </div>
        <div class="primary-score">
          <span class="score-num" :style="{ color: primaryConstitution.color }">
            {{ primaryConstitution.score }}
          </span>
          <span class="score-unit">分</span>
          <el-tag
            :type="primaryConstitution.score >= 60 ? 'danger' : 'warning'"
            size="large"
            class="score-level"
          >
            {{ getScoreLevel(primaryConstitution.score) }}
          </el-tag>
        </div>
        <div class="primary-desc">{{ primaryConstitution.description }}</div>
        <div class="primary-features">
          <div class="features-title">主要特征</div>
          <div class="features-tags">
            <el-tag
              v-for="feature in primaryConstitution.features"
              :key="feature"
              :color="primaryConstitution.color"
              effect="plain"
              size="small"
            >
              {{ feature }}
            </el-tag>
          </div>
        </div>
      </div>

      <!-- 九种体质得分 -->
      <div class="all-constitutions">
        <div class="section-title">九种体质得分</div>
        <div class="constitution-list">
          <div
            v-for="item in constitutionList"
            :key="item.name"
            class="constitution-item"
          >
            <div class="item-header">
              <span class="item-name" :style="{ color: item.color }">{{ item.name }}</span>
              <div class="item-score-group">
                <span class="item-score" :style="{ color: getScoreLevelColor(item.score) }">
                  {{ item.score }}分
                </span>
                <el-tag
                  :type="item.score >= 60 ? 'danger' : item.score >= 40 ? 'warning' : 'info'"
                  size="small"
                  effect="plain"
                >
                  {{ getScoreLevel(item.score) }}
                </el-tag>
              </div>
            </div>
            <div class="item-bar-wrapper">
              <div
                class="item-bar"
                :style="{
                  width: `${item.score}%`,
                  backgroundColor: item.color,
                }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- 调理建议 -->
      <div class="advice-section">
        <div class="section-title">调理建议</div>
        <div class="advice-content">
          <div class="advice-card">
            <el-icon color="#07c160" :size="20"><CircleCheckFilled /></el-icon>
            <div class="advice-text">
              <div class="advice-label">饮食建议</div>
              <div class="advice-desc">宜食用益气健脾食物，如山药、大枣、莲子等，避免生冷寒凉之品。</div>
            </div>
          </div>
          <div class="advice-card">
            <el-icon color="#1989fa" :size="20"><StarFilled /></el-icon>
            <div class="advice-text">
              <div class="advice-label">运动建议</div>
              <div class="advice-desc">宜选择柔和运动方式，如太极拳、八段锦、散步等，避免剧烈运动。</div>
            </div>
          </div>
          <div class="advice-card">
            <el-icon color="#7232dd" :size="20"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='20' height='20'%3E%3Cpath fill='%237232dd' d='M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.5a2 2 0 0 0 1.92-2.56l2.33-8A2 2 0 0 0 21 9H14zM2 17h3v-9H2v9z'/%3E%3C/svg%3E" style="width:20px;height:20px" /></el-icon>
            <div class="advice-text">
              <div class="advice-label">起居建议</div>
              <div class="advice-desc">保持规律作息，避免过度劳累，注意保暖，适当午休。</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 重新测试按钮 -->
      <div class="actions">
        <el-button round type="primary" @click="goToTest" class="action-btn">
          重新测试
        </el-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.constitution-page {
  min-height: 100vh;
  background-color: #f7f8fa;
  padding-bottom: 24px;
}

/* 主要体质卡片 */
.primary-card {
  background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
  margin: 12px 16px;
  border-radius: 16px;
  padding: 20px;
  border: 1px solid #fde2e2;
}

.primary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.primary-label {
  font-size: 12px;
  color: #969799;
  background-color: #fff;
  padding: 2px 8px;
  border-radius: 10px;
}

.test-date {
  font-size: 12px;
  color: #969799;
}

.primary-name {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 8px;
}

.primary-score {
  display: flex;
  align-items: baseline;
  margin-bottom: 12px;
}

.score-num {
  font-size: 36px;
  font-weight: bold;
  line-height: 1;
}

.score-unit {
  font-size: 14px;
  color: #969799;
  margin-left: 4px;
  margin-right: 12px;
}

.score-level {
  margin-left: 8px;
}

.primary-desc {
  font-size: 14px;
  color: #646566;
  line-height: 1.6;
  margin-bottom: 16px;
}

.primary-features {
  border-top: 1px dashed #ebedf0;
  padding-top: 12px;
}

.features-title {
  font-size: 13px;
  color: #969799;
  margin-bottom: 8px;
}

.features-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* 九种体质得分 */
.all-constitutions {
  margin: 16px;
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 16px;
}

.constitution-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.constitution-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-name {
  font-size: 14px;
  font-weight: 500;
}

.item-score-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.item-score {
  font-size: 14px;
  font-weight: bold;
}

.item-bar-wrapper {
  height: 6px;
  background-color: #ebedf0;
  border-radius: 3px;
  overflow: hidden;
}

.item-bar {
  height: 100%;
  border-radius: 3px;
  transition: width 0.3s ease;
}

/* 调理建议 */
.advice-section {
  margin: 16px;
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
}

.advice-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.advice-card {
  display: flex;
  align-items: flex-start;
  padding: 12px;
  background-color: #f7f8fa;
  border-radius: 8px;
  gap: 12px;
}

.advice-text {
  flex: 1;
}

.advice-label {
  font-size: 14px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 4px;
}

.advice-desc {
  font-size: 13px;
  color: #646566;
  line-height: 1.5;
}

/* 操作按钮 */
.actions {
  margin: 24px 16px;
}

.action-btn {
  width: 100%;
}
</style>
