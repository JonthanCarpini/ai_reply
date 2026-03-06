import React from 'react';
import { View, Text, ScrollView, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import { useAuth } from '../store/auth';
import { NotificationService } from '../services/notification';

interface Props {
  navigation: any;
}

export default function SettingsScreen({ navigation }: Props) {
  const { user, logout } = useAuth();

  const handleLogout = () => {
    Alert.alert('Sair', 'Deseja realmente sair?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Sair', style: 'destructive', onPress: logout },
    ]);
  };

  const sections = [
    {
      title: 'Configurações',
      items: [
        { label: 'Painel XUI', desc: 'URL e API key do painel', screen: 'PanelConfig' },
        { label: 'Inteligência Artificial', desc: 'Provedor, modelo e API key', screen: 'AIConfig' },
        { label: 'Prompts', desc: 'System prompt e mensagens', screen: 'Prompts' },
        { label: 'Ações', desc: 'Criar teste, renovar, etc', screen: 'Actions' },
        { label: 'Regras', desc: 'Horários, blacklist, rate limit', screen: 'Rules' },
      ],
    },
    {
      title: 'Conta',
      items: [
        { label: 'Meu Plano', desc: user?.subscription?.plan?.name || 'Sem plano', screen: 'Plan' },
      ],
    },
  ];

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.pageTitle}>Configurações</Text>

      {/* User card */}
      <View style={styles.userCard}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{user?.name?.[0]?.toUpperCase()}</Text>
        </View>
        <View>
          <Text style={styles.userName}>{user?.name}</Text>
          <Text style={styles.userEmail}>{user?.email}</Text>
        </View>
      </View>

      {sections.map((section) => (
        <View key={section.title} style={styles.section}>
          <Text style={styles.sectionTitle}>{section.title}</Text>
          {section.items.map((item) => (
            <TouchableOpacity key={item.label} style={styles.menuItem} onPress={() => navigation.navigate(item.screen)}>
              <View>
                <Text style={styles.menuLabel}>{item.label}</Text>
                <Text style={styles.menuDesc}>{item.desc}</Text>
              </View>
              <Text style={styles.arrow}>›</Text>
            </TouchableOpacity>
          ))}
        </View>
      ))}

      {/* Permissions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Permissões</Text>
        <TouchableOpacity style={styles.menuItem} onPress={() => NotificationService.openPermissionSettings()}>
          <Text style={styles.menuLabel}>Acesso a Notificações</Text>
          <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.menuItem} onPress={() => NotificationService.requestBatteryOptimization()}>
          <Text style={styles.menuLabel}>Otimização de Bateria</Text>
          <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
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
  section: { marginBottom: 24 },
  sectionTitle: { color: '#64748b', fontSize: 12, fontWeight: '700', textTransform: 'uppercase', marginBottom: 8, letterSpacing: 1 },
  menuItem: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: '#1e293b', borderRadius: 12, padding: 16, marginBottom: 8 },
  menuLabel: { color: '#fff', fontSize: 15, fontWeight: '600' },
  menuDesc: { color: '#64748b', fontSize: 12, marginTop: 2 },
  arrow: { color: '#64748b', fontSize: 24, fontWeight: '300' },
  logoutBtn: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, alignItems: 'center', borderWidth: 1, borderColor: '#ef4444' },
  logoutText: { color: '#ef4444', fontSize: 15, fontWeight: '700' },
});
