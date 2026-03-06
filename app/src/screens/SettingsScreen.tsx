import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, ScrollView, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, Switch } from 'react-native';
import { useAuth } from '../store/auth';
import { useSync } from '../store/sync';
import { NotificationService } from '../services/notification';
import api from '../services/api';

const WA_PKG = 'com.whatsapp';
const WA_BIZ_PKG = 'com.whatsapp.w4b';

export default function SettingsScreen() {
  const { user, logout } = useAuth();
  const { data: syncData, loading: syncing, pull, push } = useSync();
  const [whatsappEnabled, setWhatsappEnabled] = useState(true);
  const [businessEnabled, setBusinessEnabled] = useState(true);
  const [testingPanel, setTestingPanel] = useState(false);
  const [testingMessage, setTestingMessage] = useState(false);

  const loadConfig = useCallback(async () => {
    await pull();
    const pkgs = await NotificationService.getWhatsAppPackages();
    setWhatsappEnabled(pkgs.includes(WA_PKG));
    setBusinessEnabled(pkgs.includes(WA_BIZ_PKG));
  }, [pull]);

  useEffect(() => { loadConfig(); }, []);

  const handleWhatsAppToggle = async (pkg: string, value: boolean) => {
    if (pkg === WA_PKG) setWhatsappEnabled(value);
    else setBusinessEnabled(value);

    const newWa = pkg === WA_PKG ? value : whatsappEnabled;
    const newBiz = pkg === WA_BIZ_PKG ? value : businessEnabled;

    if (!newWa && !newBiz) {
      Alert.alert('Atenção', 'Pelo menos um aplicativo deve estar selecionado.');
      if (pkg === WA_PKG) setWhatsappEnabled(true);
      else setBusinessEnabled(true);
      return;
    }

    const packages: string[] = [];
    if (newWa) packages.push(WA_PKG);
    if (newBiz) packages.push(WA_BIZ_PKG);
    await NotificationService.setWhatsAppPackages(packages);
  };

  const handleTestPanel = async () => {
    if (!syncData?.panel?.panel_url || !syncData.panel.has_api_key) {
      Alert.alert('Painel não configurado', 'Configure o painel XUI no painel web primeiro.');
      return;
    }
    setTestingPanel(true);
    try {
      const { data } = await api.post('/panel/test', {
        panel_url: syncData.panel.panel_url,
        api_key: '__use_saved__',
      });
      Alert.alert(data.success ? 'Conectado' : 'Falhou', data.message);
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.message || 'Erro desconhecido';
      Alert.alert('Erro', msg);
    } finally {
      setTestingPanel(false);
      await pull();
    }
  };

  const handleTestMessage = async () => {
    setTestingMessage(true);
    try {
      const { data } = await api.post('/messages/process', {
        contact_phone: '+5500000000000',
        contact_name: 'Teste App',
        message: 'Olá, isso é um teste do app.',
      });
      const reply = data.reply || '(sem resposta)';
      Alert.alert('Resposta da IA', reply);
    } catch (e: any) {
      const status = e?.response?.status;
      const msg = e?.response?.data?.reply || e?.response?.data?.message || e?.message || 'Erro';
      if (status === 402) {
        Alert.alert('Assinatura', 'Sua assinatura expirou ou não existe. Verifique no painel web.');
      } else {
        Alert.alert('Erro', `HTTP ${status || '?'}: ${msg}`);
      }
    } finally {
      setTestingMessage(false);
    }
  };

  const handleLogout = () => {
    Alert.alert('Sair', 'Deseja realmente sair?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Sair', style: 'destructive', onPress: logout },
    ]);
  };

  const statusColor = (s?: string) => {
    if (s === 'connected') return '#22c55e';
    if (s === 'error') return '#ef4444';
    return '#f59e0b';
  };

  const statusLabel = (s?: string) => {
    if (s === 'connected') return 'Conectado';
    if (s === 'error') return 'Erro';
    return 'Não testado';
  };

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.pageTitle}>Configurações</Text>

      {/* User card */}
      <View style={styles.userCard}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{user?.name?.[0]?.toUpperCase()}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.userName}>{user?.name}</Text>
          <Text style={styles.userEmail}>{user?.email}</Text>
        </View>
        <TouchableOpacity onPress={loadConfig} style={styles.syncBtn}>
          {syncing ? <ActivityIndicator size="small" color="#6366f1" /> : <Text style={styles.syncText}>↻ Sync</Text>}
        </TouchableOpacity>
      </View>

      {/* Painel XUI Status + Test */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Painel XUI</Text>
        <View style={styles.card}>
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.menuLabel}>{syncData?.panel?.panel_name || 'Não configurado'}</Text>
              <Text style={styles.menuDesc}>{syncData?.panel?.panel_url || 'Configure no painel web'}</Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: statusColor(syncData?.panel?.status) + '20' }]}>
              <Text style={[styles.statusText, { color: statusColor(syncData?.panel?.status) }]}>
                {statusLabel(syncData?.panel?.status)}
              </Text>
            </View>
          </View>
          <TouchableOpacity
            style={[styles.actionBtn, !syncData?.panel?.has_api_key && styles.actionBtnDisabled]}
            onPress={handleTestPanel}
            disabled={testingPanel || !syncData?.panel?.has_api_key}
          >
            {testingPanel
              ? <ActivityIndicator size="small" color="#fff" />
              : <Text style={styles.actionBtnText}>Testar Conexão</Text>
            }
          </TouchableOpacity>
        </View>
      </View>

      {/* IA Config Status */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Inteligência Artificial</Text>
        <View style={styles.card}>
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.menuLabel}>
                {syncData?.ai_config?.provider ? syncData.ai_config.provider.toUpperCase() : 'Não configurado'}
              </Text>
              <Text style={styles.menuDesc}>
                {syncData?.ai_config?.model || 'Configure no painel web'}
              </Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: (syncData?.ai_config?.has_api_key ? '#22c55e' : '#f59e0b') + '20' }]}>
              <Text style={[styles.statusText, { color: syncData?.ai_config?.has_api_key ? '#22c55e' : '#f59e0b' }]}>
                {syncData?.ai_config?.has_api_key ? 'Configurado' : 'Sem API Key'}
              </Text>
            </View>
          </View>
        </View>
      </View>

      {/* WhatsApp App Selection */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Aplicativo WhatsApp</Text>
        <View style={styles.card}>
          <View style={[styles.row, { marginBottom: 12 }]}>
            <Text style={styles.menuLabel}>WhatsApp</Text>
            <Switch
              value={whatsappEnabled}
              onValueChange={(v) => handleWhatsAppToggle(WA_PKG, v)}
              trackColor={{ false: '#334155', true: '#22c55e80' }}
              thumbColor={whatsappEnabled ? '#22c55e' : '#64748b'}
            />
          </View>
          <View style={styles.row}>
            <Text style={styles.menuLabel}>WhatsApp Business</Text>
            <Switch
              value={businessEnabled}
              onValueChange={(v) => handleWhatsAppToggle(WA_BIZ_PKG, v)}
              trackColor={{ false: '#334155', true: '#22c55e80' }}
              thumbColor={businessEnabled ? '#22c55e' : '#64748b'}
            />
          </View>
        </View>
      </View>

      {/* Teste de Mensagem */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Teste de Mensagem</Text>
        <View style={styles.card}>
          <Text style={styles.menuDesc}>
            Simula o envio de uma mensagem para a API e mostra a resposta da IA.
            Testa: autenticação, assinatura, prompt, provedor de IA.
          </Text>
          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#8b5cf6', marginTop: 12 }]}
            onPress={handleTestMessage}
            disabled={testingMessage}
          >
            {testingMessage
              ? <ActivityIndicator size="small" color="#fff" />
              : <Text style={styles.actionBtnText}>Enviar Mensagem de Teste</Text>
            }
          </TouchableOpacity>
        </View>
      </View>

      {/* Prompts & Actions Summary */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Configurações Sincronizadas</Text>
        <View style={styles.card}>
          <View style={[styles.row, { marginBottom: 8 }]}>
            <Text style={styles.menuLabel}>Prompts</Text>
            <Text style={styles.menuDesc}>{syncData?.prompts?.length ?? 0} configurado(s)</Text>
          </View>
          <View style={[styles.row, { marginBottom: 8 }]}>
            <Text style={styles.menuLabel}>Ações</Text>
            <Text style={styles.menuDesc}>
              {syncData?.actions?.filter(a => a.enabled).length ?? 0} de {syncData?.actions?.length ?? 0} ativas
            </Text>
          </View>
          <View style={styles.row}>
            <Text style={styles.menuLabel}>Regras</Text>
            <Text style={styles.menuDesc}>
              {syncData?.rules?.filter(r => r.enabled).length ?? 0} de {syncData?.rules?.length ?? 0} ativas
            </Text>
          </View>
        </View>
      </View>

      {/* Permissions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Permissões</Text>
        <TouchableOpacity style={styles.menuItem} onPress={() => NotificationService.openAppSettings()}>
          <View>
            <Text style={styles.menuLabel}>Configurações do App</Text>
            <Text style={styles.menuDesc}>Permitir configurações restritas</Text>
          </View>
          <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.menuItem} onPress={() => NotificationService.openPermissionSettings()}>
          <View>
            <Text style={styles.menuLabel}>Acesso a Notificações</Text>
            <Text style={styles.menuDesc}>Ativar listener do WhatsApp</Text>
          </View>
          <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.menuItem} onPress={() => NotificationService.requestBatteryOptimization()}>
          <View>
            <Text style={styles.menuLabel}>Otimização de Bateria</Text>
            <Text style={styles.menuDesc}>Desativar para manter serviço ativo</Text>
          </View>
          <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
      </View>

      {/* Conta */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Conta</Text>
        <View style={styles.menuItem}>
          <View>
            <Text style={styles.menuLabel}>Meu Plano</Text>
            <Text style={styles.menuDesc}>{user?.subscription?.plan?.name || 'Sem plano'}</Text>
          </View>
          <Text style={[styles.statusText, { color: user?.subscription?.status === 'active' || user?.subscription?.status === 'trial' ? '#22c55e' : '#ef4444' }]}>
            {user?.subscription?.status === 'active' ? 'Ativo' : user?.subscription?.status === 'trial' ? 'Trial' : 'Inativo'}
          </Text>
        </View>
      </View>

      <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
        <Text style={styles.logoutText}>Sair da Conta</Text>
      </TouchableOpacity>

      <View style={{ height: 40 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0f172a', padding: 16 },
  pageTitle: { fontSize: 24, fontWeight: '800', color: '#fff', marginTop: 16, marginBottom: 20 },
  userCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#1e293b', borderRadius: 16, padding: 16, gap: 16, marginBottom: 24 },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#6366f1', justifyContent: 'center', alignItems: 'center' },
  avatarText: { color: '#fff', fontSize: 20, fontWeight: '800' },
  userName: { color: '#fff', fontSize: 16, fontWeight: '700' },
  userEmail: { color: '#94a3b8', fontSize: 13 },
  syncBtn: { paddingHorizontal: 12, paddingVertical: 6, backgroundColor: '#334155', borderRadius: 8 },
  syncText: { color: '#6366f1', fontSize: 13, fontWeight: '700' },
  section: { marginBottom: 24 },
  sectionTitle: { color: '#64748b', fontSize: 12, fontWeight: '700', textTransform: 'uppercase', marginBottom: 8, letterSpacing: 1 },
  card: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, borderWidth: 1, borderColor: '#334155' },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  menuItem: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: '#1e293b', borderRadius: 12, padding: 16, marginBottom: 8 },
  menuLabel: { color: '#fff', fontSize: 15, fontWeight: '600' },
  menuDesc: { color: '#64748b', fontSize: 12, marginTop: 2 },
  arrow: { color: '#64748b', fontSize: 24, fontWeight: '300' },
  statusBadge: { borderRadius: 8, paddingHorizontal: 10, paddingVertical: 4 },
  statusText: { fontSize: 12, fontWeight: '700' },
  actionBtn: { backgroundColor: '#6366f1', borderRadius: 10, padding: 12, alignItems: 'center', marginTop: 12 },
  actionBtnDisabled: { backgroundColor: '#334155' },
  actionBtnText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  logoutBtn: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, alignItems: 'center', borderWidth: 1, borderColor: '#ef4444' },
  logoutText: { color: '#ef4444', fontSize: 15, fontWeight: '700' },
});
