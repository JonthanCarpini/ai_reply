import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, RefreshControl } from 'react-native';
import { useAuth } from '../store/auth';
import { useService } from '../store/service';
import { NotificationService } from '../services/notification';
import api from '../services/api';
import { DashboardStats } from '../types';

export default function HomeScreen() {
  const user = useAuth((s) => s.user);
  const { status, refreshStatus, toggleService } = useService();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = async () => {
    try {
      const { data } = await api.get('/dashboard/stats');
      setStats(data);
    } catch {}
    await refreshStatus();
  };

  useEffect(() => { loadData(); }, []);

  const onRefresh = async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  };

  const handlePermission = () => NotificationService.openPermissionSettings();
  const handleBattery = () => NotificationService.requestBatteryOptimization();

  return (
    <ScrollView style={styles.container} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#6366f1" />}>
      <Text style={styles.greeting}>Olá, {user?.name?.split(' ')[0]} 👋</Text>

      {/* Service Toggle */}
      <View style={[styles.card, { borderColor: status.isRunning ? '#22c55e' : '#ef4444' }]}>
        <View style={styles.row}>
          <View>
            <Text style={styles.cardTitle}>Serviço de Resposta</Text>
            <Text style={[styles.statusText, { color: status.isRunning ? '#22c55e' : '#ef4444' }]}>
              {status.isRunning ? '● Ativo' : '○ Inativo'}
            </Text>
          </View>
          <TouchableOpacity style={[styles.toggleBtn, { backgroundColor: status.isRunning ? '#ef4444' : '#22c55e' }]} onPress={toggleService}>
            <Text style={styles.toggleText}>{status.isRunning ? 'Parar' : 'Iniciar'}</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Permission warnings */}
      {!status.hasPermission && (
        <View style={[styles.card, styles.warningCard]}>
          <Text style={styles.warningText}>⚠️ Permissão de notificação necessária</Text>
          <Text style={styles.warningDesc}>Siga os 2 passos abaixo:</Text>

          <TouchableOpacity style={styles.stepBtn} onPress={() => NotificationService.openAppSettings()}>
            <Text style={styles.stepBtnText}>Passo 1: Abrir Config do App</Text>
            <Text style={styles.stepHint}>Toque em (⋮) → "Permitir configurações restritas"</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.stepBtn, { backgroundColor: '#1e3a5f' }]} onPress={handlePermission}>
            <Text style={styles.stepBtnText}>Passo 2: Ativar Acesso a Notificações</Text>
            <Text style={styles.stepHint}>Ative o toggle do AIReplyApp</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Stats */}
      <Text style={styles.sectionTitle}>Hoje</Text>
      <View style={styles.statsGrid}>
        {[
          { label: 'Conversas', value: stats?.conversations_today ?? 0, color: '#6366f1' },
          { label: 'Mensagens', value: stats?.messages_today ?? 0, color: '#8b5cf6' },
          { label: 'Ações', value: stats?.actions_today ?? 0, color: '#22c55e' },
          { label: 'Tokens IA', value: stats?.ai_tokens_today ?? 0, color: '#f59e0b' },
        ].map((s) => (
          <View key={s.label} style={styles.statCard}>
            <Text style={[styles.statValue, { color: s.color }]}>{s.value}</Text>
            <Text style={styles.statLabel}>{s.label}</Text>
          </View>
        ))}
      </View>

      {/* Plan info */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Plano</Text>
        <Text style={styles.planName}>{user?.subscription?.plan?.name || 'Sem plano'}</Text>
        <Text style={styles.planStatus}>{user?.subscription?.status === 'active' ? '✅ Ativo' : '⚠️ Inativo'}</Text>
      </View>

      <View style={{ height: 40 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0f172a', padding: 16 },
  greeting: { fontSize: 24, fontWeight: '800', color: '#fff', marginTop: 16, marginBottom: 20 },
  card: { backgroundColor: '#1e293b', borderRadius: 16, padding: 20, marginBottom: 16, borderWidth: 1, borderColor: '#334155' },
  cardTitle: { fontSize: 16, fontWeight: '700', color: '#fff' },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  statusText: { fontSize: 14, marginTop: 4, fontWeight: '600' },
  toggleBtn: { borderRadius: 12, paddingHorizontal: 20, paddingVertical: 10 },
  toggleText: { color: '#fff', fontWeight: '700', fontSize: 14 },
  warningCard: { borderColor: '#f59e0b', backgroundColor: '#1c1917' },
  warningText: { color: '#fbbf24', fontSize: 14, fontWeight: '700' },
  warningDesc: { color: '#a3a3a3', fontSize: 12, marginTop: 4 },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: '#fff', marginBottom: 12 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: 16 },
  statCard: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, width: '47%', borderWidth: 1, borderColor: '#334155' },
  statValue: { fontSize: 28, fontWeight: '800' },
  statLabel: { fontSize: 12, color: '#94a3b8', marginTop: 4 },
  planName: { fontSize: 20, fontWeight: '700', color: '#6366f1', marginTop: 8 },
  planStatus: { fontSize: 13, color: '#94a3b8', marginTop: 4 },
  stepBtn: { backgroundColor: '#1e3a2f', borderRadius: 12, padding: 14, marginTop: 12 },
  stepBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
  stepHint: { color: '#94a3b8', fontSize: 12, marginTop: 4 },
});
